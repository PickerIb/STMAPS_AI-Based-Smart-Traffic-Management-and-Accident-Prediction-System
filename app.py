from flask import Flask, render_template, request, jsonify
import warnings
warnings.filterwarnings('ignore')

from prediction_engine import AIPredictionEngine

app = Flask(__name__)

# Initialize advanced engine (trained lazily on first use)
engine = AIPredictionEngine()
engine_trained = False

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/train', methods=['POST'])
def train_model():
    try:
        global engine_trained
        training_result = engine.train_model()
        engine_trained = True
        return jsonify({
            'success': True,
            'message': 'Model trained successfully!',
            'metrics': training_result
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
        
        # Ensure engine is trained (lazily)
        global engine_trained
        if not engine_trained:
            engine.train_model()
            engine_trained = True
        
        # Make enhanced prediction (includes analysis and recommendations)
        result = engine.predict_performance(data)
        
        return jsonify(result)
        
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
