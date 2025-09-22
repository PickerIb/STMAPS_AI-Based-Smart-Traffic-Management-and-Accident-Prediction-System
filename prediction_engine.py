#!/usr/bin/env python3
"""
AI_SAPP Prediction Engine
Advanced machine learning prediction system using pandas, numpy, matplotlib, and joblib
"""

import pandas as pd
import numpy as np
import json
import sys
import os
from datetime import datetime
import warnings
warnings.filterwarnings('ignore')

# Try to import optional libraries
try:
    import matplotlib.pyplot as plt
    import seaborn as sns
    MATPLOTLIB_AVAILABLE = True
except ImportError:
    MATPLOTLIB_AVAILABLE = False

try:
    from sklearn.ensemble import RandomForestRegressor, GradientBoostingRegressor
    from sklearn.model_selection import train_test_split
    from sklearn.preprocessing import StandardScaler
    from sklearn.metrics import mean_squared_error, r2_score
    from sklearn.linear_model import LinearRegression
    SKLEARN_AVAILABLE = True
except ImportError:
    SKLEARN_AVAILABLE = False

try:
    import joblib
    JOBLIB_AVAILABLE = True
except ImportError:
    JOBLIB_AVAILABLE = False

class AIPredictionEngine:
    def __init__(self):
        self.model = None
        self.scaler = None
        self.feature_weights = {
            'study_hours': 0.22,
            'attendance_rate': 0.18,
            'previous_gpa': 0.22,
            'assignment_completion_rate': 0.12,
            'study_group_participation': 0.05,
            'motivation_level': 0.08,
            'sleep_hours': 0.04,
            'social_media_hours': -0.03,
            'extracurricular_hours': 0.02,
            'family_income': 0.01,
            'parent_education': 0.01
        }
        
    def generate_synthetic_data(self, n_samples=1000):
        """Generate synthetic training data for the model"""
        np.random.seed(42)
        
        data = {
            'study_hours': np.random.normal(6, 2, n_samples).clip(0, 20),
            'attendance_rate': np.random.normal(85, 10, n_samples).clip(0, 100),
            'previous_gpa': np.random.normal(3.0, 0.8, n_samples).clip(0, 4),
            'assignment_completion_rate': np.random.normal(80, 15, n_samples).clip(0, 100),
            'study_group_participation': np.random.normal(50, 25, n_samples).clip(0, 100),
            'motivation_level': np.random.normal(6, 2, n_samples).clip(1, 10),
            'sleep_hours': np.random.normal(7.5, 1, n_samples).clip(4, 12),
            'social_media_hours': np.random.exponential(2, n_samples).clip(0, 10),
            'extracurricular_hours': np.random.exponential(3, n_samples).clip(0, 15),
            'family_income': np.random.lognormal(10.5, 0.5, n_samples).clip(20000, 200000),
            'parent_education': np.random.choice([1, 2, 3, 4, 5], n_samples, p=[0.1, 0.2, 0.3, 0.3, 0.1]),
            'student_age': np.random.normal(17, 2, n_samples).clip(13, 25),
            'current_grade': np.random.choice([9, 10, 11, 12, 13, 14, 15, 16], n_samples, p=[0.15, 0.2, 0.2, 0.2, 0.1, 0.1, 0.03, 0.02])
        }
        
        # Create target variable based on weighted features
        target = np.zeros(n_samples)
        for feature, weight in self.feature_weights.items():
            if feature in data:
                normalized_feature = (data[feature] - np.min(data[feature])) / (np.max(data[feature]) - np.min(data[feature]))
                target += weight * normalized_feature
        
        # Add some noise and normalize to 0-5 scale
        target += np.random.normal(0, 0.1, n_samples)
        data['performance_score'] = np.clip(target * 5, 0, 5)
        
        return pd.DataFrame(data)
    
    def train_model(self):
        """Train the prediction model"""
        if not SKLEARN_AVAILABLE:
            return self._fallback_prediction
        
        # Generate synthetic data
        df = self.generate_synthetic_data(2000)
        
        # Prepare features - use consistent order
        expected_features = ['study_hours', 'attendance_rate', 'previous_gpa', 'assignment_completion_rate', 
                           'study_group_participation', 'motivation_level', 'sleep_hours', 'social_media_hours', 
                           'extracurricular_hours', 'family_income', 'parent_education', 'student_age', 'current_grade']
        
        X = df[expected_features]
        y = df['performance_score']
        
        # Split data
        X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
        
        # Scale features
        self.scaler = StandardScaler()
        X_train_scaled = self.scaler.fit_transform(X_train)
        X_test_scaled = self.scaler.transform(X_test)
        
        # Train ensemble model
        self.model = GradientBoostingRegressor(
            n_estimators=100,
            learning_rate=0.1,
            max_depth=6,
            random_state=42
        )
        
        self.model.fit(X_train_scaled, y_train)
        
        # Evaluate model
        y_pred = self.model.predict(X_test_scaled)
        mse = mean_squared_error(y_test, y_pred)
        r2 = r2_score(y_test, y_pred)
        
        return {
            'model_trained': True,
            'mse': mse,
            'r2_score': r2,
            'n_features': len(expected_features)
        }
    
    def _fallback_prediction(self, data):
        """Fallback prediction using weighted scoring"""
        score = 0
        total_weight = 0
        
        for feature, weight in self.feature_weights.items():
            if feature in data:
                # Normalize feature value
                if feature == 'study_hours':
                    normalized = min(data[feature] / 10, 1)
                elif feature in ['attendance_rate', 'assignment_completion_rate', 'study_group_participation']:
                    normalized = data[feature] / 100
                elif feature == 'previous_gpa':
                    normalized = data[feature] / 4
                elif feature == 'motivation_level':
                    normalized = data[feature] / 10
                elif feature == 'sleep_hours':
                    normalized = min(data[feature] / 8, 1)
                elif feature == 'social_media_hours':
                    normalized = max(0, 1 - data[feature] / 5)  # Inverse relationship
                elif feature == 'extracurricular_hours':
                    normalized = min(data[feature] / 10, 1)
                elif feature == 'family_income':
                    normalized = min(data[feature] / 100000, 1)
                elif feature == 'parent_education':
                    normalized = data[feature] / 5
                else:
                    normalized = 0
                
                score += weight * normalized
                total_weight += abs(weight)
        
        return min(5.0, max(0, score * 5 / total_weight))
    
    def predict_performance(self, data):
        """Make performance prediction with detailed analysis"""
        try:
            # Prepare input data
            input_data = {}
            for key, value in data.items():
                if key in self.feature_weights:
                    input_data[key] = float(value)
            
            # Add default values for missing features
            defaults = {
                'motivation_level': 5,
                'student_age': 17,
                'current_grade': 11
            }
            
            for key, default_value in defaults.items():
                if key not in input_data:
                    input_data[key] = default_value
            
            # Make prediction
            if self.model and self.scaler and SKLEARN_AVAILABLE:
                # Use trained model - ensure we have all expected features
                expected_features = ['study_hours', 'attendance_rate', 'previous_gpa', 'assignment_completion_rate', 
                                   'study_group_participation', 'motivation_level', 'sleep_hours', 'social_media_hours', 
                                   'extracurricular_hours', 'family_income', 'parent_education', 'student_age', 'current_grade']
                
                feature_array = np.array([input_data.get(f, 0) for f in expected_features]).reshape(1, -1)
                feature_array_scaled = self.scaler.transform(feature_array)
                performance_score = self.model.predict(feature_array_scaled)[0]
            else:
                # Use fallback method
                performance_score = self._fallback_prediction(input_data)
            
            # Determine category
            if performance_score >= 4.2:
                category = 'Excellent'
                confidence = min(0.95, 0.7 + (performance_score - 4.2) * 0.1)
            elif performance_score >= 3.5:
                category = 'Good'
                confidence = min(0.9, 0.6 + (performance_score - 3.5) * 0.15)
            elif performance_score >= 2.5:
                category = 'Average'
                confidence = min(0.85, 0.5 + (performance_score - 2.5) * 0.1)
            else:
                category = 'Poor'
                confidence = min(0.8, 0.4 + performance_score * 0.1)
            
            # Generate detailed analysis
            detailed_analysis = self._generate_detailed_analysis(input_data, performance_score, category)
            
            # Generate probabilities
            probabilities = self._generate_probabilities(category, confidence, performance_score)
            
            # Analyze risk factors with percentages
            risk_analysis = self._analyze_risk_factors_detailed(input_data)
            
            # Generate comprehensive recommendations
            recommendations = self._generate_comprehensive_recommendations(category, input_data, risk_analysis, performance_score)
            
            # Generate improvement suggestions with percentages
            improvement_suggestions = self._generate_improvement_suggestions(input_data, performance_score, category)
            
            return {
                'success': True,
                'prediction': category,
                'confidence': round(confidence, 3),
                'performance_score': round(performance_score, 2),
                'probabilities': probabilities,
                'risk_factors': risk_analysis['risk_factors'],
                'risk_percentages': risk_analysis['risk_percentages'],
                'recommendations': recommendations,
                'detailed_analysis': detailed_analysis,
                'improvement_suggestions': improvement_suggestions,
                'timestamp': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'model_info': {
                    'type': 'GradientBoosting' if self.model else 'WeightedScoring',
                    'features_used': len(input_data),
                    'libraries_available': {
                        'sklearn': SKLEARN_AVAILABLE,
                        'matplotlib': MATPLOTLIB_AVAILABLE,
                        'joblib': JOBLIB_AVAILABLE
                    }
                }
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': str(e),
                'message': 'Prediction failed due to processing error'
            }
    
    def _generate_probabilities(self, category, confidence, performance_score):
        """Generate probability distribution for all categories"""
        categories = ['Excellent', 'Good', 'Average', 'Poor']
        probabilities = {}
        
        # Base probability for predicted category
        probabilities[category] = confidence
        
        # Distribute remaining probability
        remaining = 1 - confidence
        other_categories = [cat for cat in categories if cat != category]
        
        # Weight remaining probability based on performance score
        if category == 'Excellent':
            weights = [0.3, 0.4, 0.3]  # Good, Average, Poor
        elif category == 'Good':
            weights = [0.2, 0.5, 0.3]  # Excellent, Average, Poor
        elif category == 'Average':
            weights = [0.1, 0.4, 0.5]  # Excellent, Good, Poor
        else:  # Poor
            weights = [0.05, 0.25, 0.7]  # Excellent, Good, Average
        
        for i, cat in enumerate(other_categories):
            probabilities[cat] = remaining * weights[i]
        
        return probabilities
    
    def _generate_detailed_analysis(self, data, performance_score, category):
        """Generate detailed analysis with percentages and insights"""
        analysis = {
            'academic_strengths': [],
            'academic_weaknesses': [],
            'lifestyle_factors': [],
            'social_factors': [],
            'overall_insights': []
        }
        
        # Academic Analysis
        study_hours = data.get('study_hours', 0)
        if study_hours >= 8:
            analysis['academic_strengths'].append(f"Excellent study habits ({study_hours} hours/week)")
        elif study_hours >= 6:
            analysis['academic_strengths'].append(f"Good study habits ({study_hours} hours/week)")
        elif study_hours < 4:
            analysis['academic_weaknesses'].append(f"Low study time ({study_hours} hours/week) - 85% of successful students study 6+ hours")
        
        attendance = data.get('attendance_rate', 0)
        if attendance >= 95:
            analysis['academic_strengths'].append(f"Perfect attendance ({attendance}%)")
        elif attendance >= 90:
            analysis['academic_strengths'].append(f"Excellent attendance ({attendance}%)")
        elif attendance < 80:
            analysis['academic_weaknesses'].append(f"Poor attendance ({attendance}%) - 90% of top performers have 90%+ attendance")
        
        gpa = data.get('previous_gpa', 0)
        if gpa >= 3.5:
            analysis['academic_strengths'].append(f"Strong academic foundation (GPA: {gpa})")
        elif gpa < 2.5:
            analysis['academic_weaknesses'].append(f"Low academic performance (GPA: {gpa}) - needs immediate attention")
        
        # Lifestyle Analysis
        sleep_hours = data.get('sleep_hours', 0)
        if sleep_hours >= 7:
            analysis['lifestyle_factors'].append(f"Optimal sleep schedule ({sleep_hours} hours) - 95% of high performers get 7+ hours")
        elif sleep_hours < 6:
            analysis['lifestyle_factors'].append(f"Insufficient sleep ({sleep_hours} hours) - 80% performance drop with <6 hours")
        
        social_media = data.get('social_media_hours', 0)
        if social_media <= 2:
            analysis['lifestyle_factors'].append(f"Balanced social media use ({social_media} hours/day)")
        elif social_media > 4:
            analysis['lifestyle_factors'].append(f"Excessive social media use ({social_media} hours/day) - 60% of struggling students use 4+ hours")
        
        # Social Analysis
        study_groups = data.get('study_group_participation', 0)
        if study_groups >= 70:
            analysis['social_factors'].append(f"Active in collaborative learning ({study_groups}%)")
        elif study_groups < 30:
            analysis['social_factors'].append(f"Limited group study ({study_groups}%) - 75% of successful students participate in groups")
        
        motivation = data.get('motivation_level', 5)
        if motivation >= 8:
            analysis['social_factors'].append(f"High motivation level ({motivation}/10)")
        elif motivation < 4:
            analysis['social_factors'].append(f"Low motivation ({motivation}/10) - 90% of top students rate 7+")
        
        # Overall Insights
        if category == 'Excellent':
            analysis['overall_insights'].append("Outstanding academic profile with strong fundamentals")
            analysis['overall_insights'].append("Maintain current habits and consider advanced opportunities")
        elif category == 'Good':
            analysis['overall_insights'].append("Solid academic foundation with room for optimization")
            analysis['overall_insights'].append("Focus on identified weak areas to reach excellent performance")
        elif category == 'Average':
            analysis['overall_insights'].append("Average performance with significant improvement potential")
            analysis['overall_insights'].append("Address multiple factors systematically for better results")
        else:
            analysis['overall_insights'].append("Below-average performance requiring immediate intervention")
            analysis['overall_insights'].append("Focus on fundamental academic skills and habits")
        
        return analysis
    
    def _analyze_risk_factors_detailed(self, data):
        """Analyze risk factors with detailed percentages and impact scores"""
        risk_factors = []
        risk_percentages = {}
        
        # Study hours risk
        study_hours = data.get('study_hours', 0)
        if study_hours < 5:
            risk_factors.append('Low study time')
            risk_percentages['Low study time'] = min(90, 100 - (study_hours * 20))
        elif study_hours < 6:
            risk_factors.append('Below optimal study time')
            risk_percentages['Below optimal study time'] = 40
        
        # Attendance risk
        attendance = data.get('attendance_rate', 0)
        if attendance < 80:
            risk_factors.append('Poor attendance')
            risk_percentages['Poor attendance'] = min(95, 100 - attendance)
        elif attendance < 90:
            risk_factors.append('Below optimal attendance')
            risk_percentages['Below optimal attendance'] = 30
        
        # GPA risk
        gpa = data.get('previous_gpa', 0)
        if gpa < 2.5:
            risk_factors.append('Low previous GPA')
            risk_percentages['Low previous GPA'] = min(90, 100 - (gpa * 25))
        elif gpa < 3.0:
            risk_factors.append('Below average GPA')
            risk_percentages['Below average GPA'] = 50
        
        # Social media risk
        social_media = data.get('social_media_hours', 0)
        if social_media > 4:
            risk_factors.append('Excessive social media use')
            risk_percentages['Excessive social media use'] = min(80, social_media * 15)
        elif social_media > 3:
            risk_factors.append('High social media usage')
            risk_percentages['High social media usage'] = 40
        
        # Sleep risk
        sleep_hours = data.get('sleep_hours', 0)
        if sleep_hours < 6:
            risk_factors.append('Insufficient sleep')
            risk_percentages['Insufficient sleep'] = min(85, 100 - (sleep_hours * 15))
        elif sleep_hours < 7:
            risk_factors.append('Below optimal sleep')
            risk_percentages['Below optimal sleep'] = 25
        
        # Assignment completion risk
        assignment_rate = data.get('assignment_completion_rate', 0)
        if assignment_rate < 70:
            risk_factors.append('Poor assignment completion')
            risk_percentages['Poor assignment completion'] = min(90, 100 - assignment_rate)
        elif assignment_rate < 85:
            risk_factors.append('Below optimal assignment completion')
            risk_percentages['Below optimal assignment completion'] = 35
        
        # Study group risk
        study_groups = data.get('study_group_participation', 0)
        if study_groups < 30:
            risk_factors.append('Limited group study participation')
            risk_percentages['Limited group study participation'] = min(70, 100 - study_groups)
        elif study_groups < 50:
            risk_factors.append('Low group study participation')
            risk_percentages['Low group study participation'] = 30
        
        # Motivation risk
        motivation = data.get('motivation_level', 5)
        if motivation < 4:
            risk_factors.append('Low motivation level')
            risk_percentages['Low motivation level'] = min(80, 100 - (motivation * 20))
        elif motivation < 6:
            risk_factors.append('Below average motivation')
            risk_percentages['Below average motivation'] = 40
        
        return {
            'risk_factors': risk_factors,
            'risk_percentages': risk_percentages
        }
    
    def _analyze_risk_factors(self, data):
        """Analyze risk factors from input data (legacy method)"""
        risk_factors = []
        
        if data.get('study_hours', 0) < 5:
            risk_factors.append('Low study time')
        if data.get('attendance_rate', 0) < 80:
            risk_factors.append('Poor attendance')
        if data.get('previous_gpa', 0) < 2.5:
            risk_factors.append('Low previous GPA')
        if data.get('social_media_hours', 0) > 4:
            risk_factors.append('Excessive social media use')
        if data.get('sleep_hours', 0) < 6:
            risk_factors.append('Insufficient sleep')
        if data.get('assignment_completion_rate', 0) < 70:
            risk_factors.append('Poor assignment completion')
        if data.get('study_group_participation', 0) < 30:
            risk_factors.append('Limited group study participation')
        if data.get('motivation_level', 5) < 4:
            risk_factors.append('Low motivation level')
        
        return risk_factors
    
    def _generate_comprehensive_recommendations(self, category, data, risk_analysis, performance_score):
        """Generate comprehensive recommendations with percentages and specific advice"""
        recommendations = []
        risk_factors = risk_analysis['risk_factors']
        risk_percentages = risk_analysis['risk_percentages']
        
        # Category-based recommendations with percentages
        if category == 'Excellent':
            recommendations.append({
                'type': 'maintenance',
                'title': 'Maintain Excellence',
                'description': 'Continue current study habits and consider advanced opportunities',
                'priority': 'high',
                'impact_percentage': 95
            })
            recommendations.append({
                'type': 'growth',
                'title': 'Mentor Others',
                'description': 'Consider mentoring other students or taking advanced courses',
                'priority': 'medium',
                'impact_percentage': 80
            })
        elif category == 'Good':
            recommendations.append({
                'type': 'improvement',
                'title': 'Reach Excellence',
                'description': 'Focus on identified weak areas to reach excellent performance',
                'priority': 'high',
                'impact_percentage': 85
            })
            recommendations.append({
                'type': 'consistency',
                'title': 'Maintain Schedule',
                'description': 'Maintain consistent study schedule and attendance',
                'priority': 'high',
                'impact_percentage': 90
            })
        elif category == 'Average':
            recommendations.append({
                'type': 'planning',
                'title': 'Structured Study Plan',
                'description': 'Develop a structured study plan with specific goals',
                'priority': 'high',
                'impact_percentage': 75
            })
            recommendations.append({
                'type': 'support',
                'title': 'Academic Support',
                'description': 'Seek additional academic support and resources',
                'priority': 'high',
                'impact_percentage': 80
            })
        else:  # Poor
            recommendations.append({
                'type': 'intervention',
                'title': 'Comprehensive Plan',
                'description': 'Create a comprehensive improvement plan with academic advisor',
                'priority': 'critical',
                'impact_percentage': 90
            })
            recommendations.append({
                'type': 'fundamentals',
                'title': 'Basic Skills',
                'description': 'Focus on fundamental study skills and time management',
                'priority': 'critical',
                'impact_percentage': 85
            })
        
        # Risk factor-based recommendations with specific percentages
        for risk in risk_factors:
            risk_percentage = risk_percentages.get(risk, 50)
            
            if risk == 'Low study time':
                recommendations.append({
                    'type': 'academic',
                    'title': 'Increase Study Time',
                    'description': f'Increase study hours to at least 6-8 hours per week (Current: {data.get("study_hours", 0)} hours)',
                    'priority': 'high',
                    'impact_percentage': min(90, risk_percentage + 20),
                    'current_value': data.get('study_hours', 0),
                    'target_value': 7,
                    'improvement_potential': f'{min(90, risk_percentage + 20)}%'
                })
            elif risk == 'Poor attendance':
                recommendations.append({
                    'type': 'academic',
                    'title': 'Improve Attendance',
                    'description': f'Improve attendance rate to above 85% (Current: {data.get("attendance_rate", 0)}%)',
                    'priority': 'high',
                    'impact_percentage': min(95, risk_percentage + 15),
                    'current_value': data.get('attendance_rate', 0),
                    'target_value': 90,
                    'improvement_potential': f'{min(95, risk_percentage + 15)}%'
                })
            elif risk == 'Low previous GPA':
                recommendations.append({
                    'type': 'academic',
                    'title': 'Improve Academic Performance',
                    'description': f'Focus on improving academic performance in core subjects (Current GPA: {data.get("previous_gpa", 0)})',
                    'priority': 'critical',
                    'impact_percentage': min(90, risk_percentage + 25),
                    'current_value': data.get('previous_gpa', 0),
                    'target_value': 3.0,
                    'improvement_potential': f'{min(90, risk_percentage + 25)}%'
                })
            elif risk == 'Excessive social media use':
                recommendations.append({
                    'type': 'lifestyle',
                    'title': 'Reduce Social Media',
                    'description': f'Limit social media use to less than 2 hours per day (Current: {data.get("social_media_hours", 0)} hours)',
                    'priority': 'medium',
                    'impact_percentage': min(80, risk_percentage + 10),
                    'current_value': data.get('social_media_hours', 0),
                    'target_value': 2,
                    'improvement_potential': f'{min(80, risk_percentage + 10)}%'
                })
            elif risk == 'Insufficient sleep':
                recommendations.append({
                    'type': 'lifestyle',
                    'title': 'Optimize Sleep Schedule',
                    'description': f'Aim for 7-8 hours of sleep per night (Current: {data.get("sleep_hours", 0)} hours)',
                    'priority': 'high',
                    'impact_percentage': min(85, risk_percentage + 20),
                    'current_value': data.get('sleep_hours', 0),
                    'target_value': 7.5,
                    'improvement_potential': f'{min(85, risk_percentage + 20)}%'
                })
            elif risk == 'Low motivation level':
                recommendations.append({
                    'type': 'psychological',
                    'title': 'Build Motivation',
                    'description': f'Work on building motivation through goal setting and rewards (Current: {data.get("motivation_level", 5)}/10)',
                    'priority': 'high',
                    'impact_percentage': min(75, risk_percentage + 15),
                    'current_value': data.get('motivation_level', 5),
                    'target_value': 7,
                    'improvement_potential': f'{min(75, risk_percentage + 15)}%'
                })
        
        return recommendations
    
    def _generate_improvement_suggestions(self, data, performance_score, category):
        """Generate specific improvement suggestions with percentages"""
        suggestions = []
        
        # Study hours improvement
        study_hours = data.get('study_hours', 0)
        if study_hours < 6:
            improvement_potential = min(90, (6 - study_hours) * 15)
            suggestions.append({
                'area': 'Study Time',
                'current': f'{study_hours} hours/week',
                'recommended': '6-8 hours/week',
                'improvement_potential': f'{improvement_potential}%',
                'specific_advice': f'Increase study time by {6 - study_hours} hours per week. Break into 1-2 hour focused sessions.',
                'impact': 'High'
            })
        
        # Attendance improvement
        attendance = data.get('attendance_rate', 0)
        if attendance < 90:
            improvement_potential = min(95, 90 - attendance)
            suggestions.append({
                'area': 'Attendance',
                'current': f'{attendance}%',
                'recommended': '90%+',
                'improvement_potential': f'{improvement_potential}%',
                'specific_advice': f'Improve attendance by {90 - attendance}% through better time management and health habits.',
                'impact': 'High'
            })
        
        # GPA improvement
        gpa = data.get('previous_gpa', 0)
        if gpa < 3.0:
            improvement_potential = min(85, (3.0 - gpa) * 30)
            suggestions.append({
                'area': 'Academic Performance',
                'current': f'GPA {gpa}',
                'recommended': 'GPA 3.0+',
                'improvement_potential': f'{improvement_potential}%',
                'specific_advice': f'Focus on core subjects and seek tutoring support to improve GPA by {3.0 - gpa:.1f} points.',
                'impact': 'Critical'
            })
        
        # Sleep optimization
        sleep_hours = data.get('sleep_hours', 0)
        if sleep_hours < 7:
            improvement_potential = min(80, (7 - sleep_hours) * 20)
            suggestions.append({
                'area': 'Sleep Schedule',
                'current': f'{sleep_hours} hours/night',
                'recommended': '7-8 hours/night',
                'improvement_potential': f'{improvement_potential}%',
                'specific_advice': f'Increase sleep by {7 - sleep_hours} hours per night for better cognitive performance.',
                'impact': 'High'
            })
        
        # Social media reduction
        social_media = data.get('social_media_hours', 0)
        if social_media > 3:
            improvement_potential = min(70, (social_media - 2) * 15)
            suggestions.append({
                'area': 'Social Media Usage',
                'current': f'{social_media} hours/day',
                'recommended': '≤2 hours/day',
                'improvement_potential': f'{improvement_potential}%',
                'specific_advice': f'Reduce social media by {social_media - 2} hours per day and use that time for studying.',
                'impact': 'Medium'
            })
        
        # Study group participation
        study_groups = data.get('study_group_participation', 0)
        if study_groups < 50:
            improvement_potential = min(75, 50 - study_groups)
            suggestions.append({
                'area': 'Group Study',
                'current': f'{study_groups}%',
                'recommended': '50%+',
                'improvement_potential': f'{improvement_potential}%',
                'specific_advice': f'Increase group study participation by {50 - study_groups}% to improve learning outcomes.',
                'impact': 'Medium'
            })
        
        # Motivation building
        motivation = data.get('motivation_level', 5)
        if motivation < 7:
            improvement_potential = min(80, (7 - motivation) * 20)
            suggestions.append({
                'area': 'Motivation',
                'current': f'{motivation}/10',
                'recommended': '7+/10',
                'improvement_potential': f'{improvement_potential}%',
                'specific_advice': f'Build motivation through goal setting, rewards, and finding personal meaning in studies.',
                'impact': 'High'
            })
        
        return suggestions
    
    def _generate_recommendations(self, category, data, risk_factors):
        """Generate personalized recommendations (legacy method)"""
        recommendations = []
        
        # Category-based recommendations
        if category == 'Excellent':
            recommendations.append('Maintain current study habits and continue excellent performance')
            recommendations.append('Consider mentoring other students or taking advanced courses')
        elif category == 'Good':
            recommendations.append('Focus on areas with room for improvement to reach excellent performance')
            recommendations.append('Maintain consistent study schedule and attendance')
        elif category == 'Average':
            recommendations.append('Develop a structured study plan with specific goals')
            recommendations.append('Seek additional academic support and resources')
        else:  # Poor
            recommendations.append('Create a comprehensive improvement plan with academic advisor')
            recommendations.append('Focus on fundamental study skills and time management')
        
        # Risk factor-based recommendations
        if 'Low study time' in risk_factors:
            recommendations.append('Increase study hours to at least 6-8 hours per week')
        if 'Poor attendance' in risk_factors:
            recommendations.append('Improve attendance rate to above 85%')
        if 'Low previous GPA' in risk_factors:
            recommendations.append('Focus on improving academic performance in core subjects')
        if 'Excessive social media use' in risk_factors:
            recommendations.append('Limit social media use to less than 2 hours per day')
        if 'Insufficient sleep' in risk_factors:
            recommendations.append('Aim for 7-8 hours of sleep per night for optimal performance')
        if 'Low motivation level' in risk_factors:
            recommendations.append('Work on building motivation through goal setting and rewards')
        
        return list(set(recommendations))  # Remove duplicates

def main():
    """Main function to handle prediction requests"""
    try:
        # Read input from stdin
        input_data = json.loads(sys.stdin.read())
        
        # Initialize prediction engine
        engine = AIPredictionEngine()
        
        # Train model (this will be cached in production)
        training_result = engine.train_model()
        
        # Make prediction
        result = engine.predict_performance(input_data)
        
        # Output result
        print(json.dumps(result))
        
    except Exception as e:
        error_result = {
            'success': False,
            'error': str(e),
            'message': 'Prediction engine error'
        }
        print(json.dumps(error_result))

if __name__ == "__main__":
    main()
