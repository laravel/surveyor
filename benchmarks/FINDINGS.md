# Warm path performance: findings and plan

Everything here was measured on `laravel/cloud` (3,108 PHP files in `app/`, 617
routes, PHP 8.5.8, 12 cores) by running `php artisan wayfinder:generate`.
Numbers are medians of repeated runs with the analysis cache settled first.
Where something is a calculation rather than a measurement it says so.

## Where it stands

| | before this work | after discovery and caching | after determinism | after fingerprints |
|---|---|---|---|---|
| cold, empty cache | 17.4s | 15.6s | 18.8s | 16.3s |
| warm, nothing changed | 4.6s | 1.24s | 1.28s | 1.19s |
| warm, one edit to `Instance.php` | 7.0s | 3.8s | 4.1s | 1.7s |

The first column predates all of this. The rest are medians of three runs each,
taken back to back on the same machine. The cache is 62MB, down from 278MB.

Which file you edit still decides the edit path, and closing that spread is what
step 2 below is for:

| touched | entries rewritten (of 3,149) | time |
|---|---|---|
| `app/Models/Instance.php` | 61 | 2.0s |
| `app/Models/User.php` | 147 | 2.0s |
| `app/Models/Environment.php` | 230 | 2.7s |
| `app/Models/Organization.php` | 319 | 2.9s |

One run each, and this is what fingerprinting bought: the same four used to
rewrite 258, 1,370, 1,366 and 1,370 entries and take 3.7s, 11.2s, 16.9s and
19.5s. What is left is the file's own dependents whose surfaces really did move.

Analysis is now deterministic: the same bytes give the same answers whatever
order the files are visited in. That was the blocker on fingerprinting, so
step 2 can start.

## What landed

**Ranger v0.4.0, one shared structure index.** Five collectors each ran their
own `Discover::in()` over the whole application, so every build read all 3,108
files five times. Instrumenting `Discover::get()` in a real run: five calls,
3.718s of a 4.76s build, whether or not anything had changed. `Support\Inventory`
now reads the paths once and remembers what it found per file, so a build pays
only for files that changed. Discovery went from 0.744s to 0.026s warm. Output
byte identical, checked field by field against the old path over all 3,108 files.

**Surveyor `Type::union` no longer marks its arguments nullable in place.**
It set the flag on the objects it was handed, and one of those can be the type
already recorded for a model property. Resolving an unrelated union then flipped
a stored type to nullable for the rest of the run. Reading a cached analysis goes
through `unserialize()`, which hands back a fresh graph, so warm builds could not
reach the shared object and were correct while cold builds were wrong. Cold and
warm output differed by 124 lines across 6 files, showing up as nullable route
keys and needless null guards. Now zero.

**Wayfinder sets Ranger's cache directory** and clears it on `--fresh`. Without
this Ranger writes no index and none of the above applies.

## Determinism: what it was, and what fixed it

Four changes, in the working tree, not yet committed. Together: no flapping
surfaces on any target measured, warm output equal to cold across every shape
of source change, 288 tests passing, and 15 generated declarations that used to
name a type nothing declared now carry the real shape.

**One traverser per parse.** This was the whole of it. `Parser` added a
`NameResolver` and a `TypeResolver` to a single `NodeTraverser` in its
constructor, and `Analyzer` is a singleton holding one `Parser`. Resolving a
node can analyze another file, which lands back in `Parser::parseCode()` while
the outer traversal is still running, so the nested file walked the same
traverser and the same two visitors. php-parser's `NameResolver` starts each
traversal with a fresh `NameContext`, so when control came back the outer file's
remaining names resolved against the nested file's imports.

What that looked like in the wild: `app/Support/DeploymentLogParser.php` imports
`Illuminate\Support\Carbon` and has `timeSince(?Carbon $startedAt)`. On a cold
build the recorded parameter type was `App\Support\Carbon`, a different class
that also exists. Once the imports are gone, `Scope::getUse()` guesses the
file's own namespace before it walks up to the parent scope that holds the
imports, and the guess exists, so it wins. A warm build parses fewer files, so
nothing clobbered the context and the answer was right. Same bytes, two answers.

Touching `app/Models/User.php` without changing a byte, then rebuilding: 8 of
3,158 entries came back with a different public surface, every one of them a
short class name landing on a different fully qualified name. Three were read
line by line, `Carbon`, `Builder` and `DatabaseSnapshot`, and in all three the
cold answer was the wrong one, resolved against some other file's imports.

Each parse now builds its own traverser and visitors. That took the 8 to 1, and
dropped 10 vendor files from the build, files only ever reached because a name
resolved to the wrong class.

**Cycle members are analyzed again once the cycle closes.** The one remaining
flap was the empty-scope bail-out already described below. A cold build has
1,763 of them across 80 cycles, and the cycles hold 333 members between them.
`app/Models/Zone.php` is the root of one cycle with 91 members, `Environment.php`
among them, which is why `Environment::vanityDomainZone()` recorded `mixed` for
`Zone::freeTrial()` on a cold build and `App\Models\Zone` on a warm one: a method
returning `static` resolves against the scope of the class it was found on, and
during a cycle that scope is empty.

`AnalyzedCache` already knew which entries it had held back while a cycle was
open. It now hands that list to the analyzer, which invalidates and re-analyzes
one member at a time with the rest left in the cache, so each of them sees a
finished answer for everything it reaches. That is the cheap spike the plan
asked for, and it worked: no surface flaps left. It costs 333 extra analyses,
which is the 15.6s to 18.8s on the cold column above, and 2 to 3s on the edit
path for a file with many dependents. Editing `Instance.php` rewrites fewer
entries than it used to, 258 against 312, because a dependency list recorded
outside a cycle is narrower, but that does not show up as time saved.

**Fluent calls keep their receiver.** Settling the cycles made
`DeploymentResource::make($x)->forParentEnvironment($y)` resolve, and the answer
came back as a bare class name where the generated types then had nothing to
point at. `forParentEnvironment(): static` returns the object it was called on,
but reflection only reports which class that is, so the resource response and
the shape it carries were thrown away and replaced with a plain class. A method
call whose reflected return type is the same class as its receiver now hands the
receiver straight back when the receiver knows more than its class name.

It also fixed three references that were dangling before any of this work:
`App.Http.Resources.Dashboard.EnvironmentResource`, `CacheResource` and
`FilesystemResource` were named in the generated types and declared nowhere. 15
declarations gained a real shape, one new response type appeared, and nothing
was lost. One dangling name is left, `NonWrappedCollection`.

**The reflector puts the scope back.** `Reflector::methodReturnType` swapped the
shared reflector's scope to another class's scope and never restored it, so the
next file resolved its own short class names against whatever class was looked at
last. The generics branch a few lines below already had a `$scopeToRestore`
guard, so the hazard was known. Restoring it before determinism landed made
things worse, which is why it was parked. Done after: output byte identical,
determinism still clean, all tests pass.

## Plan

1. ~~**Determinism.**~~ Done, above. The deliverable is
   `tests/Unit/Analyzer/DeterminismTest.php` with fixtures in
   `workbench/app/Determinism`: one test that a file's own imports survive
   analyzing another file, one that the surface is the same whichever file is
   analyzed first, one that a cycle member is answered against the finished
   analysis of the other member. Each fails without the change it covers.

2. **Fingerprint invalidation.** Done, in three slices. Store **direct**
   dependencies only, each keyed by that dependency's public surface hash rather
   than its modification time or its bytes. A body edit changes the file's own
   hash so that file is re-analysed, its surface is unchanged, so its dependents
   stay valid.

   | touched | rewritten before | rewritten now | time before | time now |
   |---|---|---|---|---|
   | `Instance.php` | 258 | 61 | 3.7s | 2.0s |
   | `User.php` | 1,370 | 147 | 11.2s | 2.0s |
   | `Environment.php` | 1,366 | 230 | 16.9s | 2.7s |
   | `Organization.php` | 1,370 | 319 | 19.5s | 2.9s |

   Cold and warm are unchanged, 17.5s to 19.1s and 1.2s to 1.35s. The cache went
   from 290MB to 62MB. Touching a file without editing it rewrites 3 entries
   where it used to rewrite 1,366.

   All seven shapes in `shapesweep.sh` agree between warm and cold, including a
   new body-only edit shape that exists to test exactly what this bets on.

   Measured ceiling, on a settled cache:

   | edit | re-analysed | results that changed |
   |---|---|---|
   | `touch User.php`, bytes identical | 1,374 | 5 |
   | `Environment.php` body-only edit | 1,370 | 5 |
   | `Environment.php` new public method | 1,370 | 1 |

   So 99.6% of the re-analysis produces byte identical results. Adding a public
   method changed exactly one surface, the edited file's own, which is the
   property the design needs. At roughly 9.6ms per entry that is about 13s of
   wasted work on a central model. This is a ceiling from measured redundancy,
   not a measurement of an implementation.

   Cache size falls out of this for free. Of 272MB, 85% is dependency
   bookkeeping: 1.6M path and mtime pairs. Dependencies **recorded** per entry:
   median 92, mean 489, max 1,803. Dependencies **actually used**: median 2,
   mean 4, max 63. The closure is 129 times the direct edges. The estimate from
   that was roughly 42MB; it came out at 62MB, 12MB of which is the records
   taking a filesystem block each.

   The closure was there so an entry could be validated by stat'ing a flat list
   without walking the graph. Nothing replaces that: the walk came back, over
   records rather than entries. See the slice 3 note below.

   **Slice 1 is done.** `Analyzer\Surface` is the one definition of what a
   dependent can see, `surfacedump.php` calls it rather than keeping its own
   copy, and `tests/Unit/Analyzer/SurfaceTest.php` pins the properties the
   design needs: a body edit leaves the hash alone, a new public method or a
   changed return type or a changed import moves it.

   It also turned up the hole that would have sunk the design. 205 of 3,149
   entries record no result at all, almost all of them enums: nothing analyzes
   an enum's cases, dependents read them off PHP's reflection, so 89 enums in
   `App\Enums` all hashed the same and an enum edit moved nothing. Files with
   nothing observable now hash their own bytes instead, which makes any edit to
   them count. All 3,149 surfaces are distinct again, and the `edit-enum-case`
   shape in `shapesweep.sh` is what guards it.

   `ClassLikeResult::namespace()` was declared `: string` while the property is
   nullable, so asking a class in the global namespace for its namespace was a
   fatal error. Fixed on the way past.

   **Slice 2 is done, and slice 3 replaced its file format.** Every persisted entry now has a `<md5>.surface` file
   beside it holding its surface hash, so a dependency can be checked with one
   small read instead of unserializing its entry. `AnalyzedCache::surfaceHash()`
   answers from memory, then that file, then by hashing an entry already in
   memory. Invalidating an entry takes its surface with it.

   It costs nothing in time: cold 18.5s against 18.6s for the same tree without
   it, warm and edit unchanged. On disk the 3,149 files hold 100KB of hashes and
   occupy 12MB, all of it filesystem block overhead.

   **Slice 3 is done, and it corrected the plan above.** Direct edges cannot be
   validated by stat'ing them. With the closure gone, a dependency whose own
   bytes are untouched can still have moved, because something under *it*
   changed. Validity has to walk, so the file beside each entry holds the edges
   as well as the hash: the entry's own time and surface, and every file it
   reached with the surface that file showed. It is a `.record` now, not a
   `.surface`, and the entry itself no longer carries a dependency list at all,
   which is where the 62MB comes from.

   Deciding whether an entry holds reads records, never entries: the file is
   unchanged and every file it reached is unchanged, or one of them changed and
   has to be analyzed to find out whether the change reached its surface. Each
   answer is worked out once per run and remembered. A file with no record of
   its own has nothing underneath it, so its bytes are the whole answer. Cycles
   in the recorded graph are handled by counting a path as holding while it is
   being decided; every member is checked on its own terms anyway.

   Analyzing a changed dependency mid-check needs the analyzer, which the cache
   has no business knowing about, so the analyzer hands it a closure on
   construction. One consequence worth remembering: an analysis started from
   inside a validity check records itself against whatever frame is open, which
   over-records an edge that is real but indirect. That can only cause extra
   invalidation, never stale output.

3. **Settling narrowed to what moved.** Settling re-analyzed all 333 cycle
   members. Only 214 of them gave up on a file themselves; the other 119 merely
   asked one that did, and most of those were handed the same answer either way.
   Surface hashes make that checkable, so settling now starts with the 214 and
   pulls in a member only when a file it reached came back with a different
   surface. That took 16 more, in 7 of the 80 cycles, and never needed a third
   round: 230 re-analyses instead of 333.

   Cold went from 18.0s to 16.3s, which is 0.7s off the 15.6s from before any of
   the determinism work. Output is byte identical to settling all 333, checked
   over the whole of Cloud. Determinism holds at zero flapping surfaces across
   three targets, and all seven shapes still agree warm against cold.

   The 214 are irreducible while a file is the unit of re-analysis: each of them
   really did resolve something against nothing. Going below that means the
   surface pass and body pass split, which is a much larger change and no longer
   has a measured problem to justify it.

4. **Checked against a second application.** Everything above was fitted to
   `laravel/cloud`, so `laravel/forge` was run through the same guardrails:
   2,330 files in `app/`, 2,633 cache entries.

   | | main | this work |
   |---|---|---|
   | cold | 14.3s | 13.5s |
   | warm, nothing changed | 1.33s | 1.30s |
   | warm, method added to `Server.php` | 22.7s | 2.7s |
   | entries rewritten by that edit | 1,269 | 449 |
   | cache | 258MB | 52MB |

   Determinism holds on four targets, zero flapping surfaces, warm output equal
   to cold. All seven shapes agree. Nothing about the rules turned out to be
   specific to Cloud.

   Forge needed setting up first, and its checkout was left alone: its
   `composer.lock` has no `laravel/wayfinder`, `laravel/surveyor` or
   `laravel/ranger` in it even though `vendor` symlinks all three, so
   `composer install` there would delete them. The work was done on a copy, with
   the three packages plus `spatie/php-structure-discoverer` mapped into
   `composer.json` by hand and their providers registered in
   `bootstrap/providers.php`.

5. **What is left.**

   `StateTracker` is serialised into every cache entry. It holds the variable
   state of the run that produced the entry, nothing reads it back, and it is
   the only thing that still differs between two analyses of the same bytes: 3
   of 3,149 entries after editing `Organization.php`. It is also held in memory
   for every entry for the whole run, so dropping it from what gets persisted is
   about memory and serialisation time more than the 62MB.

   A regression test for the `Type::union` fix. It needs a shared type object
   reachable from two places, which no current test sets up.

   Debouncing the Vite plugin is done and merged.

## Dead ends, with numbers

Recorded so nobody spends a day on them again.

| idea | why not |
|---|---|
| A class-existence oracle | `Util::isClassOrInterface` costs 1.04s of a 17.4s cold run. 92,339 probes, 85,675 of them misses, 4.4us each, so the misses are 0.374s. An earlier version of this analysis claimed 34% of the cold run, taken from a sampling profiler. That was wrong. |
| Caching method return types | 101,505 calls for 17,254 distinct `class::method` pairs looks like 83% waste. The repeats cost 0.84s; the first-time lookups cost 9.93s. 123 keys also legitimately return different answers at different call sites, all generic containers like `Collection::all`. |
| Cheaper dependency bookkeeping | `addDependency` merges 35,363,504 closure entries per run and costs 0.13s. PHP's array union is fast. The size matters for disk and for invalidation width, not for CPU. |
| Reusing resolver instances | A fresh resolver is built for each of 1.9M node visits. Building all of them costs 0.17s, and reusing one while calling `setScope` measured slower. |
| Sharing the traverser between parses | It is what made analysis order dependent, and it bought nothing: building a traverser and two visitors per parse does not show up against a 15s cold run. |
| Passing changed paths in | Modification times already answer "what changed" for about 0.08s a build. Every real cost is downstream of knowing. Worth having only for a resident process, which has no sweep to fall back on. |
| Shrinking the cache on its own | 0.76s on the write side, 0.13 to 0.22s on the read side, and it fixes itself with fingerprinting. |
| Opcache CLI file cache | Slower. Boot 0.34s to 0.58s, warm build 4.54s to 4.71s. |
| A faster serializer | The full 272MB cache reads and unserialises in 0.70s, about 1.18 GB/s. |
| Tokenizer or php-parser work | Tokenising all 3,108 app files costs 0.068s. php-parser's `doParse` is under 1% of a cold run. |

## Where cold time actually goes

Instrumented per resolver with nested time subtracted, so these are exclusive:

| resolver | exclusive | visits | each |
|---|---|---|---|
| `Expr\MethodCall` | 3.59s | 98,589 | 36us |
| `Expr\StaticCall` | 2.40s | 25,918 | 93us |
| `Param` | 1.03s | 31,447 | 33us |
| `Expr\PropertyFetch` | 0.80s | 71,345 | 11us |
| 146 others | 5.5s | 1.7M | |

13.36s across 1,900,550 node visits and 150 resolver types. Method and static
call resolution is 45% of it, and inside those the time is recursion into other
files to find out what a call returns, not parsing and not reflection. There is
no hotspot on the cold path. Making it faster means doing less work, and unlike
the edit path there is no measured redundancy to remove. Settling the cycles
added 333 analyses on top, about 3.2s; that is the one place where cold work
could be trimmed, by settling fewer members or by splitting analysis into a
surface pass and a body pass so a partial answer is never observable. Otherwise
revisit only if CI time becomes the complaint.

## How to measure

All of these live in `benchmarks/cache`. They drive the app one build at a
time; two of them running at once will quietly ruin both, and a stray build
alongside a timing run reads as a regression.

- `determinism.sh <label> [target]` builds a cold cache, touches one file
  without changing it, rebuilds, and reports how many entries came back with a
  different surface. Zero is the bar.
- `SURVEYOR_BENCH_APP` points any of these at another application, and
  `SURVEYOR_BENCH_PRESET` names the set of files `mutate.py` should edit there.
  Presets are spelled out per application rather than guessed, because every
  shape has to change generated output or it proves nothing.
- `surfacedump.php <cache-dir> [--detail|--fields]` renders a cache directory
  as canonical text: one hash per entry, the surface lines themselves, or a
  hash per `Scope` property to say which part of an entry moved.
- `timeit.sh <label> [src-dir] [repeats]` copies a source tree into the app's
  vendor directory and times cold, warm and warm plus edit. Point it at
  snapshots of two trees to compare them.
- `shapesweep.sh <label>` checks warm output against cold across append,
  remove, edit, add, delete and rename. It is the guardrail for fingerprinting:
  if warm and cold disagree, the fingerprints are wrong.
- `bench.sh` and `sweepcmp.sh` predate the above and still work. Both call
  `git checkout --` inside the app, so uncommitted work there will be lost.
  `shapesweep.sh` no longer does; it copies the files it edits and puts them
  back.

The app loads Surveyor from `vendor`, not from this checkout, so a change here
reaches a build only after `rsync -a --delete src/ <app>/vendor/laravel/surveyor/src/`.
`determinism.sh` and `timeit.sh` do that themselves, and skip it when the app
symlinks the package straight at a working copy, as Forge does.

Two habits that this work depended on:

- **Settle the cache before timing.** Run until no entries are rewritten.
  Otherwise the first run reads as a regression.
- **Establish a noise floor.** Run unchanged code twice and diff the output
  before trusting any comparison.

**Do not trust a sampling profiler here.** PHP delivers async signals at VM
instruction boundaries, which piles samples onto internal calls. A sampler put
`file_get_contents` at 72% of a warm run when reading every file in the tree
actually takes 0.05s, and put class-existence checks at 34% of a cold run when
they cost 1.04s. Every number in this document that matters came from wrapping
the suspect code in `hrtime()` counters in a real run. The trick for doing that
without touching the repo: `php -d auto_prepend_file=probe.php`, where the probe
requires `vendor/autoload.php` and then requires a patched copy of the class you
want to instrument, so it is declared before Composer can autoload the original.
That does not reach a subprocess, so it will not work on tests that shell out.
