# Vera - QA Evaluation Engine

Vera is a Laravel 11-based QA evaluation engine that analyzes task runs and provides intelligent feedback including verdicts, confidence scores, retry guidance, and escalation recommendations.

## Features

- **Task Run Evaluation**: Evaluate task execution results with success/failure tracking
- **Confidence Calculation**: Dynamic confidence scoring with:
  - Multiplicative decay on failures (0.8x multiplier)
  - Diminishing success gains (exponential decay based on consecutive successes)
  - Initial confidence of 0.5, clamped between 0 and 1
- **Failure Type Classification**: Support for multiple failure types:
  - Syntax Error
  - Logic Error
  - Timeout
  - Resource Error
  - Validation Error
  - Assertion Failure
  - Unknown
- **Intelligent Retry Guidance**: 
  - Exponential backoff calculation
  - Failure-type-specific retry limits
  - Contextual action suggestions
- **Escalation Rules**:
  - Low confidence threshold (< 0.3)
  - Consecutive failure detection (≥ 3 failures)
  - Critical failure type identification
- **RESTful API**: JSON API endpoints for evaluation and retrieval

## Architecture

### Core Components

1. **Models**
   - `TaskRun`: Represents a single task execution
   - `TaskEvaluation`: Stores evaluation results and metadata

2. **Services**
   - `ConfidenceCalculationService`: Implements confidence scoring algorithm
   - `EscalationRuleService`: Determines when to escalate issues
   - `RetryGuidanceService`: Generates intelligent retry recommendations
   - `TaskEvaluationService`: Orchestrates the evaluation process

3. **Enums**
   - `FailureType`: Categorizes different types of failures

## API Documentation

### Base URL
```
http://your-domain/api/v1
```

### Endpoints

#### 1. Evaluate a Task Run
**POST** `/evaluate`

Creates a new task run and evaluates it.

**Request Body:**
```json
{
  "task_name": "string (required)",
  "success": "boolean (required)",
  "failure_type": "string (optional): syntax_error|logic_error|timeout|resource_error|validation_error|assertion_failure|unknown",
  "error_message": "string (optional)",
  "execution_time_ms": "integer (optional)",
  "metadata": "object (optional)"
}
```

**Example Request:**
```bash
curl -X POST http://localhost:8000/api/v1/evaluate \
  -H "Content-Type: application/json" \
  -d '{
    "task_name": "user_authentication",
    "success": false,
    "failure_type": "timeout",
    "error_message": "Database connection timed out",
    "execution_time_ms": 5000
  }'
```

**Response (201 Created):**
```json
{
  "task_run_id": 1,
  "verdict": "fail",
  "confidence": 0.4,
  "confidence_delta": -0.1,
  "should_escalate": true,
  "escalation_reason": "Critical failure type detected: timeout",
  "retry_guidance": {
    "should_retry": true,
    "suggested_delay_seconds": 5,
    "max_retry_attempts": 2,
    "suggested_actions": [
      "Increase timeout limit",
      "Optimize query performance",
      "Check for deadlocks"
    ]
  },
  "evaluation_id": 1
}
```

#### 2. Get Evaluation by ID
**GET** `/evaluations/{id}`

Retrieves a specific evaluation with its associated task run.

**Example Request:**
```bash
curl http://localhost:8000/api/v1/evaluations/1
```

**Response (200 OK):**
```json
{
  "evaluation": {
    "id": 1,
    "task_run_id": 1,
    "verdict": "pass",
    "confidence": "0.6000",
    "confidence_delta": "0.1000",
    "retry_guidance": null,
    "should_escalate": false,
    "escalation_reason": null,
    "evaluation_metadata": {
      "previous_confidence": 0.5,
      "evaluation_timestamp": "2026-02-11T19:09:48+00:00"
    },
    "created_at": "2026-02-11T19:09:48.000000Z",
    "updated_at": "2026-02-11T19:09:48.000000Z",
    "task_run": { }
  },
  "task_run": { }
}
```

#### 3. List Evaluations
**GET** `/evaluations?task_name={task_name}`

Lists evaluations with optional filtering by task name.

**Query Parameters:**
- `task_name` (optional): Filter evaluations by task name
- `page` (optional): Page number for pagination

**Example Request:**
```bash
curl "http://localhost:8000/api/v1/evaluations?task_name=user_authentication"
```

**Response (200 OK):**
```json
{
  "current_page": 1,
  "data": [
    { }
  ],
  "first_page_url": "...",
  "last_page_url": "...",
  "next_page_url": "...",
  "per_page": 20,
  "total": 42
}
```

## Installation

### Prerequisites
- PHP 8.2 or higher
- Composer
- SQLite (default) or MySQL/PostgreSQL

### Setup

1. Clone the repository:
```bash
git clone https://github.com/CraigThomasParsons/TheQAQueue.git
cd TheQAQueue
```

2. Install dependencies:
```bash
composer install
```

3. Create environment file:
```bash
cp .env.example .env
```

4. Generate application key:
```bash
php artisan key:generate
```

5. Run migrations:
```bash
php artisan migrate
```

6. Start the development server:
```bash
php artisan serve
```

The API will be available at `http://localhost:8000/api/v1`

## Testing

Run the test suite:
```bash
php artisan test
```

Run specific test suites:
```bash
# Feature tests only
php artisan test --testsuite=Feature

# Unit tests only
php artisan test --testsuite=Unit
```

## Confidence Algorithm Details

### Initial State
- All tasks start with a confidence of 0.5

### On Success
```
gain = 0.1 × (0.9 ^ consecutive_successes)
new_confidence = current_confidence + gain
```

### On Failure
```
new_confidence = current_confidence × 0.8
```

### Bounds
- Confidence is always clamped between 0.0 and 1.0

## Escalation Rules

A task is escalated when any of these conditions are met:

1. **Low Confidence**: Confidence drops below 0.3
2. **Consecutive Failures**: 3 or more consecutive failures
3. **Critical Failure Type**: Timeout or Resource Error detected

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
