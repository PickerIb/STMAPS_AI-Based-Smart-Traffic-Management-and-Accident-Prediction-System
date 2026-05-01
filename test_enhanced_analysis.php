<?php
// Test the enhanced AI prediction system with detailed analysis
echo "Testing Enhanced AI Prediction System with Detailed Analysis...\n";
echo "==============================================================\n\n";

// Test data with various scenarios
$test_scenarios = [
    [
        'name' => 'High Performer',
        'data' => [
            'study_hours' => 8.5,
            'attendance_rate' => 95,
            'previous_gpa' => 3.8,
            'extracurricular_hours' => 4.0,
            'family_income' => 90000,
            'parent_education' => 4,
            'sleep_hours' => 8.0,
            'social_media_hours' => 1.5,
            'study_group_participation' => 80,
            'assignment_completion_rate' => 95,
            'motivation_level' => 9,
            'student_age' => 17,
            'current_grade' => 11,
            'learning_style' => 'visual'
        ]
    ],
    [
        'name' => 'Average Performer',
        'data' => [
            'study_hours' => 5.0,
            'attendance_rate' => 85,
            'previous_gpa' => 2.8,
            'extracurricular_hours' => 3.0,
            'family_income' => 55000,
            'parent_education' => 3,
            'sleep_hours' => 6.5,
            'social_media_hours' => 3.0,
            'study_group_participation' => 40,
            'assignment_completion_rate' => 75,
            'motivation_level' => 6,
            'student_age' => 16,
            'current_grade' => 10,
            'learning_style' => 'mixed'
        ]
    ],
    [
        'name' => 'Struggling Student',
        'data' => [
            'study_hours' => 3.0,
            'attendance_rate' => 70,
            'previous_gpa' => 2.0,
            'extracurricular_hours' => 1.0,
            'family_income' => 35000,
            'parent_education' => 2,
            'sleep_hours' => 5.0,
            'social_media_hours' => 6.0,
            'study_group_participation' => 20,
            'assignment_completion_rate' => 60,
            'motivation_level' => 3,
            'student_age' => 15,
            'current_grade' => 9,
            'learning_style' => 'kinesthetic'
        ]
    ]
];

// Test each scenario
foreach ($test_scenarios as $scenario) {
    echo "Testing Scenario: {$scenario['name']}\n";
    echo str_repeat("-", 50) . "\n";
    
    // Test the Python prediction engine function
    require_once 'index.php';
    
    $result = callPythonPredictionEngine($scenario['data']);
    
    if ($result['success']) {
        echo "✅ Prediction successful!\n";
        echo "Prediction: {$result['prediction']}\n";
        echo "Confidence: " . round($result['confidence'] * 100, 1) . "%\n";
        echo "Performance Score: {$result['performance_score']}/5.0\n";
        echo "Model Type: {$result['model_info']['type']}\n\n";
        
        // Display detailed analysis
        if (isset($result['detailed_analysis'])) {
            echo "📊 DETAILED ANALYSIS:\n";
            echo "Academic Strengths: " . count($result['detailed_analysis']['academic_strengths']) . " items\n";
            echo "Academic Weaknesses: " . count($result['detailed_analysis']['academic_weaknesses']) . " items\n";
            echo "Lifestyle Factors: " . count($result['detailed_analysis']['lifestyle_factors']) . " items\n";
            echo "Social Factors: " . count($result['detailed_analysis']['social_factors']) . " items\n\n";
        }
        
        // Display risk factors with percentages
        if (isset($result['risk_percentages'])) {
            echo "⚠️  RISK FACTORS:\n";
            foreach ($result['risk_percentages'] as $risk => $percentage) {
                echo "- $risk: $percentage% risk\n";
            }
            echo "\n";
        }
        
        // Display improvement suggestions
        if (isset($result['improvement_suggestions'])) {
            echo "🎯 IMPROVEMENT SUGGESTIONS:\n";
            foreach ($result['improvement_suggestions'] as $suggestion) {
                echo "- {$suggestion['area']}: {$suggestion['improvement_potential']} potential\n";
                echo "  Current: {$suggestion['current']} → Recommended: {$suggestion['recommended']}\n";
                echo "  Advice: {$suggestion['specific_advice']}\n\n";
            }
        }
        
        echo str_repeat("=", 60) . "\n\n";
    } else {
        echo "❌ Prediction failed: {$result['error']}\n";
        echo "Message: {$result['message']}\n\n";
    }
}

?>
