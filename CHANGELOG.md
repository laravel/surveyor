# Changelog

All notable changes to this project will be documented in this file.

## v0.3.0 - 2026-08-22

### What's Changed

* Stop Type::union marking its arguments nullable in place by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/75
* Make analysis deterministic by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/76
* Recognize ignore markers during analysis by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/77
* Validate cached analyses against dependency surfaces by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/78
* Update benchmarks by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/80
* Type union regression test by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/81

**Full Changelog**: https://github.com/laravel/surveyor/compare/v0.2.8...v0.3.0

## v0.2.8 - 2026-08-18

### What's Changed

* Resolve Eloquent attributes built with Attribute::get() or new Attribute() by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/67
* Type singular model relations as nullable by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/70
* Resolve ternary branches instead of the compared value by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/71
* Invalidate the analysis cache per file by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/72
* Stop string literals from becoming class types by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/73

**Full Changelog**: https://github.com/laravel/surveyor/compare/v0.2.7...v0.2.8

## v0.2.7 - 2026-08-12

### What's Changed

* Add Dependabot cooldown of 5 days by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/surveyor/pull/51
* Enable Dependabot auto-merge by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/surveyor/pull/52
* Bump actions/checkout from 6.0.2 to 6.0.3 in the github-actions group by [@dependabot](https://github.com/dependabot)[bot] in https://github.com/laravel/surveyor/pull/53
* Bump shivammathur/setup-php from 2.37.1 to 2.37.2 in the github-actions group by [@dependabot](https://github.com/dependabot)[bot] in https://github.com/laravel/surveyor/pull/55
* Bump actions/checkout from 6.0.3 to 7.0.0 in the github-actions group by [@dependabot](https://github.com/dependabot)[bot] in https://github.com/laravel/surveyor/pull/57
* Bump actions/checkout from 7.0.0 to 7.0.1 in the github-actions group by [@dependabot](https://github.com/dependabot)[bot] in https://github.com/laravel/surveyor/pull/61
* fix: resolve array cast to ArrayType instead of ArrayShapeType by [@alaminfirdows](https://github.com/alaminfirdows) in https://github.com/laravel/surveyor/pull/60
* Fix analyzer state when given an empty path by [@Button99](https://github.com/Button99) in https://github.com/laravel/surveyor/pull/63
* Plethora of performance improvements by [@ryangjchandler](https://github.com/ryangjchandler) in https://github.com/laravel/surveyor/pull/65
* `composer update` by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/66

### New Contributors

* [@alaminfirdows](https://github.com/alaminfirdows) made their first contribution in https://github.com/laravel/surveyor/pull/60
* [@Button99](https://github.com/Button99) made their first contribution in https://github.com/laravel/surveyor/pull/63

**Full Changelog**: https://github.com/laravel/surveyor/compare/v0.2.6...v0.2.7

## v0.2.6 - 2026-06-02

### What's Changed

* Fix PHP warning when a cached dependency file no longer exists by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/50

**Full Changelog**: https://github.com/laravel/surveyor/compare/v0.2.5...v0.2.6

## v0.2.5 - 2026-05-23

### What's Changed

* Bump shivammathur/setup-php from 2.37.0 to 2.37.1 in the github-actions group by [@dependabot](https://github.com/dependabot)[bot] in https://github.com/laravel/surveyor/pull/45
* Resolve Inertia special prop types (defer, optional, lazy, always, merge) by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/48
* Allow [@var](https://github.com/var) docblocks on array items to override inferred type by [@JasBogans](https://github.com/JasBogans) in https://github.com/laravel/surveyor/pull/41
* Run tests in parallel by default by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/49

### New Contributors

* [@dependabot](https://github.com/dependabot)[bot] made their first contribution in https://github.com/laravel/surveyor/pull/45
* [@JasBogans](https://github.com/JasBogans) made their first contribution in https://github.com/laravel/surveyor/pull/41

**Full Changelog**: https://github.com/laravel/surveyor/compare/v0.2.4...v0.2.5

## v0.2.4 - 2026-05-18

### What's Changed

* Analyze interfaces and harden class analysis by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/40
* Fix method resolution for names that are PHP type keywords by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/43
* Fix Eloquent builder method resolution via [@mixin](https://github.com/mixin) and integer range types by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/44
* Pin GitHub Actions to commit SHAs and add Dependabot config by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/42
* Fix generics propagation through Eloquent Builder method chains by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/46

**Full Changelog**: https://github.com/laravel/surveyor/compare/v0.2.3...v0.2.4

## v0.2.3 - 2026-05-07

### What's Changed

* Treat corrupted disk cache files as a miss by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/36
* Preserve associative keys when resolving compact() by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/37
* Don't union AnonymousResourceCollection into Resource::collection() return type by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/38

**Full Changelog**: https://github.com/laravel/surveyor/compare/v0.2.2...v0.2.3

## v0.2.2 - 2026-05-06

### What's Changed

* Resolve facades to their root class for method reflection by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/35

**Full Changelog**: https://github.com/laravel/surveyor/compare/v0.2.1...v0.2.2

## v0.2.1 - 2026-05-04

### What's Changed

* Expose class-level [@property](https://github.com/property) docblock tags as properties by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/34

**Full Changelog**: https://github.com/laravel/surveyor/compare/v0.2.0...v0.2.1

## v0.2.0 - 2026-05-04

### What's Changed

* Create CHANGELOG.md by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/31
* [0.x] Bump checkout action by [@jackbayliss](https://github.com/jackbayliss) in https://github.com/laravel/surveyor/pull/33
* Analyze API resource responses by [@joetannenbaum](https://github.com/joetannenbaum) in https://github.com/laravel/surveyor/pull/32

### New Contributors

* [@jackbayliss](https://github.com/jackbayliss) made their first contribution in https://github.com/laravel/surveyor/pull/33

**Full Changelog**: https://github.com/laravel/surveyor/compare/v0.1.10...v0.2.0

## [Unreleased]

## [v0.1.10] - 2026-04-30

### What's Changed

* Handle inline variable assignments inside arrays by @bakerkretzmar in https://github.com/laravel/surveyor/pull/30

### New Contributors

* @bakerkretzmar made their first contribution in https://github.com/laravel/surveyor/pull/30

**Full Changelog**: https://github.com/laravel/surveyor/compare/v0.1.9...v0.1.10
