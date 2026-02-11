<?php

namespace App;

enum FailureType: string
{
    case SYNTAX_ERROR = 'syntax_error';
    case LOGIC_ERROR = 'logic_error';
    case TIMEOUT = 'timeout';
    case RESOURCE_ERROR = 'resource_error';
    case VALIDATION_ERROR = 'validation_error';
    case ASSERTION_FAILURE = 'assertion_failure';
    case UNKNOWN = 'unknown';
}
