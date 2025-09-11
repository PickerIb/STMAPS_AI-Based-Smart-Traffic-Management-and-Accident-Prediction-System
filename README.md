# AI-Based Student Academic Performance Predictor

A machine learning web application that predicts student academic performance based on various factors including study habits, attendance, family background, and personal characteristics.

## Features

- **Machine Learning Model**: Uses Random Forest Classifier to predict student performance
- **Modern Web Interface**: Beautiful, responsive UI built with Flask and Bootstrap
- **Comprehensive Input**: Collects 10 different factors that influence academic performance
- **Real-time Predictions**: Instant performance predictions with confidence scores
- **Sample Data**: Built-in sample data for testing and demonstration

## Performance Categories

The model predicts student performance into four categories:
- **Excellent**: Outstanding academic performance
- **Good**: Above-average performance
- **Average**: Standard performance
- **Poor**: Below-average performance

## Input Features

### Academic Factors
- Study hours per day
- Attendance rate
- Previous GPA
- Assignment completion rate

### Personal & Family
- Family income
- Parent education level
- Sleep hours per night

### Social & Activities
- Extracurricular hours per week
- Social media hours per day
- Study group participation

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd AI_SAPP
   ```

2. **Install dependencies**
   ```bash
   pip install -r requirements.txt
   ```

3. **Run the application**
   ```bash
   python app.py
   ```

4. **Access the application**
   Open your browser and go to `http://localhost:5000`

## Usage

1. **Train the Model**: Click "Train Model" to train the machine learning model with sample data
2. **Input Student Data**: Fill in the student information form with relevant details
3. **Load Sample Data**: Use the "Load Sample Data" button to populate the form with example data
4. **Get Prediction**: Click "Predict Performance" to get the performance prediction and confidence scores

## Technical Details

### Model Architecture
- **Algorithm**: Random Forest Classifier
- **Features**: 10 standardized input features
- **Data**: 1000 synthetic student records for training
- **Validation**: 80/20 train-test split with stratified sampling

### Performance Metrics
- Accuracy score
- Classification report with precision, recall, and F1-score
- Confidence scores for each prediction category

### Web Framework
- **Backend**: Flask (Python)
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Styling**: Custom CSS with gradient backgrounds and modern UI elements

## File Structure

```
AI_SAPP/
├── app.py                 # Main Flask application
├── templates/
│   └── index.html        # Web interface template
├── requirements.txt      # Python dependencies
└── README.md            # Project documentation
```

## API Endpoints

- `GET /` - Main application interface
- `POST /train` - Train the machine learning model
- `POST /predict` - Make performance predictions
- `GET /sample_data` - Get sample student data

## Model Performance

The model achieves high accuracy by considering multiple factors:
- Academic performance indicators (study hours, attendance, GPA)
- Personal habits (sleep, social media usage)
- Family background (income, education)
- Social engagement (extracurricular activities, study groups)

## Future Enhancements

- Real student data integration
- Additional machine learning algorithms
- Performance trend analysis
- Student recommendation system
- Data visualization dashboard
- Export functionality for predictions

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This project is open source and available under the MIT License.

## Contact

For questions or support, please contact the development team.


