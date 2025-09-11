from flask import Flask, render_template, request, jsonify
import pandas as pd
import numpy as np
import joblib
import os
from sklearn.model_selection import train_test_split
from sklearn.ensemble import RandomForestClassifier
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import accuracy_score, classification_report
import warnings
warnings.filterwarnings('ignore')

app = Flask(__name__)

class StudentPerformancePredictor:
    def __init__(self):
        self.model = None
        self.scaler = StandardScaler()
        self.feature_columns = [
            'study_hours', 'attendance_rate', 'previous_gpa', 'extracurricular_hours',
            'family_income', 'parent_education', 'sleep_hours', 'social_media_hours',
            'study_group_participation', 'assignment_completion_rate'
        ]
        
    def generate_sample_data(self, n_samples=1000):
        """Generate sample student data for training"""
        np.random.seed(42)
        
        data = {
            'study_hours': np.random.normal(3.5, 1.5, n_samples).clip(0, 8),
            'attendance_rate': np.random.beta(8, 2, n_samples),
            'previous_gpa': np.random.normal(3.2, 0.8, n_samples).clip(0, 4),
            'extracurricular_hours': np.random.exponential(2, n_samples).clip(0, 10),
            'family_income': np.random.lognormal(10, 0.5, n_samples),
            'parent_education': np.random.choice([1, 2, 3, 4], n_samples, p=[0.1, 0.3, 0.4, 0.2]),
            'sleep_hours': np.random.normal(7.5, 1.5, n_samples).clip(4, 12),
            'social_media_hours': np.random.exponential(2.5, n_samples).clip(0, 8),
            'study_group_participation': np.random.beta(3, 7, n_samples),
            'assignment_completion_rate': np.random.beta(8, 2, n_samples)
        }
        
        df = pd.DataFrame(data)
        
        # Create performance categories based on weighted combination of features
        performance_score = (
            df['study_hours'] * 0.2 +
            df['attendance_rate'] * 0.15 +
            df['previous_gpa'] * 0.25 +
            df['assignment_completion_rate'] * 0.2 +
            df['study_group_participation'] * 0.1 +
            (8 - df['social_media_hours']) * 0.05 +
            (df['sleep_hours'] - 4) * 0.05
        )
        
        # Convert to performance categories
        df['performance_category'] = pd.cut(
            performance_score, 
            bins=[0, 2.5, 3.5, 4.5, 5], 
            labels=['Poor', 'Average', 'Good', 'Excellent']
        )
        
        return df
    
    def train_model(self):
        """Train the prediction model"""
        # Generate sample data
        df = self.generate_sample_data()
        
        # Prepare features and target
        X = df[self.feature_columns]
        y = df['performance_category']
        
        # Split data
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42, stratify=y
        )
        
        # Scale features
        X_train_scaled = self.scaler.fit_transform(X_train)
        X_test_scaled = self.scaler.transform(X_test)
        
        # Train model
        self.model = RandomForestClassifier(
            n_estimators=100,
            random_state=42,
            max_depth=10,
            min_samples_split=5
        )
        self.model.fit(X_train_scaled, y_train)
        
        # Evaluate model
        y_pred = self.model.predict(X_test_scaled)
        accuracy = accuracy_score(y_test, y_pred)
        
        print(f"Model trained successfully!")
        print(f"Accuracy: {accuracy:.3f}")
        print("\nClassification Report:")
        print(classification_report(y_test, y_pred))
        
        return accuracy
    
    def predict_performance(self, student_data):
        """Predict student performance"""
        if self.model is None:
            return "Model not trained yet"
        
        # Convert to DataFrame
        df = pd.DataFrame([student_data])
        
        # Ensure all required columns are present
        for col in self.feature_columns:
            if col not in df.columns:
                df[col] = 0
        
        # Scale features
        X_scaled = self.scaler.transform(df[self.feature_columns])
        
        # Make prediction
        prediction = self.model.predict(X_scaled)[0]
        probabilities = self.model.predict_proba(X_scaled)[0]
        
        # Get class labels
        class_labels = self.model.classes_
        
        # Create result dictionary
        result = {
            'prediction': prediction,
            'probabilities': dict(zip(class_labels, probabilities))
        }
        
        return result

# Initialize predictor
predictor = StudentPerformancePredictor()

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/train', methods=['POST'])
def train_model():
    try:
        accuracy = predictor.train_model()
        return jsonify({
            'success': True,
            'message': 'Model trained successfully!',
            'accuracy': accuracy
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'message': f'Error training model: {str(e)}'
        })

@app.route('/predict', methods=['POST'])
def predict():
    try:
        data = request.json
        
        # Validate required fields
        required_fields = [
            'study_hours', 'attendance_rate', 'previous_gpa', 'extracurricular_hours',
            'family_income', 'parent_education', 'sleep_hours', 'social_media_hours',
            'study_group_participation', 'assignment_completion_rate'
        ]
        
        for field in required_fields:
            if field not in data:
                return jsonify({
                    'success': False,
                    'message': f'Missing required field: {field}'
                })
        
        # Make prediction
        result = predictor.predict_performance(data)
        
        return jsonify({
            'success': True,
            'prediction': result['prediction'],
            'probabilities': result['probabilities']
        })
        
    except Exception as e:
        return jsonify({
            'success': False,
            'message': f'Error making prediction: {str(e)}'
        })

@app.route('/sample_data')
def get_sample_data():
    """Get sample student data for testing"""
    sample_data = {
        'study_hours': 4.5,
        'attendance_rate': 0.85,
        'previous_gpa': 3.2,
        'extracurricular_hours': 3.0,
        'family_income': 50000,
        'parent_education': 3,
        'sleep_hours': 7.5,
        'social_media_hours': 2.0,
        'study_group_participation': 0.6,
        'assignment_completion_rate': 0.9
    }
    return jsonify(sample_data)

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)
