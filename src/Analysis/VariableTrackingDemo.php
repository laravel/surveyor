<?php

namespace Laravel\StaticAnalyzer\Analysis;

class VariableTrackingDemo
{
    public static function demonstrateUserControllerTracking(): array
    {
        $tracker = new SimpleVariableTracker();

        // Track the $whatever variable from UserController
        $tracker->addAssignment('$whatever', 'first', 19);

        $tracker->addAssignment('$whatever', 'second', 22, [
            "request()->has('name')" => true
        ]);

        $tracker->addAssignment('$whatever', 'third', 25, [
            "request()->has('name')" => true,
            "request()->has('nameasdfsd')" => true
        ]);

        $tracker->addAssignment('$whatever', 4, 30, [
            "request()->has('name')" => false
        ]);

        return [
            'line_32_values' => $tracker->getPossibleValuesAt('$whatever', 32),
            'debug_info' => $tracker->debugPossibleValuesAt('$whatever', 32),
            'all_assignments' => $tracker->getAssignmentsSummary('$whatever')
        ];
    }

    public static function demonstrateArrayTracking(): array
    {
        $tracker = new SimpleVariableTracker();

        // Basic array assignments
        $tracker->addAssignment('$users', [], 10);
        $tracker->addAssignment('$users[0]', 'John', 15);
        $tracker->addAssignment('$users[1]', 'Jane', 20);
        $tracker->addAssignment('$users[]', 'Bob', 25); // Append

        // Conditional array modifications
        $tracker->addAssignment('$users[0]', 'Johnny', 30, [
            '$isAdmin' => true
        ]);

        // Nested array access
        $tracker->addAssignment('$data[users][0][name]', 'Alice', 35);
        $tracker->addAssignment('$data[users][0][email]', 'alice@example.com', 40);

        return [
            'users_at_line_25' => $tracker->getPossibleValuesAt('$users', 25),
            'users_0_at_line_35' => $tracker->getPossibleValuesAt('$users[0]', 35),
            'data_structure' => $tracker->getPossibleValuesAt('$data[users][0][name]', 40),
            'all_targets' => $tracker->getAllTargets()
        ];
    }

    public static function demonstrateObjectTracking(): array
    {
        $tracker = new SimpleVariableTracker();

        // Object property assignments
        $tracker->addAssignment('$user', 'new User()', 10, [], 'User');
        $tracker->addAssignment('$user->name', 'John Doe', 15);
        $tracker->addAssignment('$user->email', 'john@example.com', 20);
        $tracker->addAssignment('$user->profile', 'new Profile()', 25, [], 'Profile');
        $tracker->addAssignment('$user->profile->bio', 'Software Developer', 30);

        // Conditional property updates
        $tracker->addAssignment('$user->name', 'John Smith', 35, [
            '$user->isMarried()' => true
        ]);

        // Method call results
        $tracker->addAssignment('$user->getFullName()', 'John Doe', 40);
        $tracker->addAssignment('$user->getFullName()', 'John Smith', 45, [
            '$user->isMarried()' => true
        ]);

        return [
            'user_name_at_40' => $tracker->getPossibleValuesAt('$user->name', 40),
            'user_full_name_at_50' => $tracker->getPossibleValuesAt('$user->getFullName()', 50),
            'profile_bio' => $tracker->getPossibleValuesAt('$user->profile->bio', 35),
            'debug_user_name' => $tracker->debugPossibleValuesAt('$user->name', 50)
        ];
    }

    public static function demonstrateComplexScenario(): array
    {
        $tracker = new SimpleVariableTracker();

        // Complex nested scenario with arrays and objects
        $tracker->addAssignment('$response', '[]', 10);
        $tracker->addAssignment('$response[data]', '[]', 15);
        $tracker->addAssignment('$response[data][users]', '[]', 20);

        // Loop-like behavior
        $tracker->addAssignment('$response[data][users][]', 'new User("John")', 25);
        $tracker->addAssignment('$response[data][users][]', 'new User("Jane")', 30);

        // Conditional modifications
        $tracker->addAssignment('$response[status]', 'success', 35);
        $tracker->addAssignment('$response[status]', 'error', 40, [
            '$hasErrors' => true
        ]);

        $tracker->addAssignment('$response[message]', 'Users retrieved successfully', 45, [
            '$hasErrors' => false
        ]);

        $tracker->addAssignment('$response[message]', 'Failed to retrieve users', 50, [
            '$hasErrors' => true
        ]);

        // Nested object property access
        $tracker->addAssignment('$response[data][users][0]->status', 'active', 55);

        return [
            'response_status_at_60' => $tracker->getPossibleValuesAt('$response[status]', 60),
            'response_message_at_60' => $tracker->getPossibleValuesAt('$response[message]', 60),
            'user_status' => $tracker->getPossibleValuesAt('$response[data][users][0]->status', 60),
            'complete_debug' => $tracker->debugPossibleValuesAt('$response[status]', 60)
        ];
    }

    public static function runAllDemos(): array
    {
        return [
            'user_controller_demo' => self::demonstrateUserControllerTracking(),
            'array_tracking_demo' => self::demonstrateArrayTracking(),
            'object_tracking_demo' => self::demonstrateObjectTracking(),
            'complex_scenario_demo' => self::demonstrateComplexScenario()
        ];
    }
}
