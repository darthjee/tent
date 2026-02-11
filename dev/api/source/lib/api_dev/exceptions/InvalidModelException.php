<?php

namespace ApiDev\Exceptions;

/**
 * Exception for invalid model data.
 * 
 * Thrown when attempting to save or validate a model with invalid attributes
 * that don't meet the model's validation requirements.
 */
class InvalidModelException extends \Exception
{
}
