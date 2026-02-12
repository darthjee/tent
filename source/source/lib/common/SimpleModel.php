<?php

namespace Tent\Common;

/**
 * Base class for simple model objects with default attribute values.
 *
 * Provides a constructor that automatically assigns attributes from an array
 * using default values defined in the DEFAULT_ATTRIBUTES constant.
 *
 * Usage:
 *   class MyModel extends SimpleModel
 *   {
 *       protected const DEFAULT_ATTRIBUTES = [
 *           'name' => '',
 *           'age' => 0
 *       ];
 *
 *       private string $name;
 *       private int $age;
 *   }
 */
abstract class SimpleModel
{
    /**
     * Default values for model attributes.
     * Must be defined in child classes.
     *
     * @var array
     */
    protected const DEFAULT_ATTRIBUTES = [];

    /**
     * Constructs a SimpleModel object.
     *
     * Iterates over DEFAULT_ATTRIBUTES and assigns values from the provided data array,
     * using defaults when keys are not present. Child classes can override
     * processAttributeValue() to customize value processing for specific attributes.
     *
     * @param array $data Associative array with attribute values.
     */
    public function __construct(array $data)
    {
        foreach (static::DEFAULT_ATTRIBUTES as $key => $default) {
            $value = $data[$key] ?? $default;

            // Allow child classes to process the attribute key and value
            [$attribute, $processedValue] = $this->processAttributeValue($key, $value);

            $this->$attribute = $processedValue;
        }
    }

    /**
     * Processes an attribute key and value before assignment.
     *
     * Child classes can override this method to customize how attributes are
     * mapped or processed (e.g., instantiating default objects for null values).
     *
     * @param string $key   The attribute key from DEFAULT_ATTRIBUTES.
     * @param mixed  $value The value to be assigned (may be from data or default).
     * @return array An array with two elements: [attribute_name, processed_value].
     */
    protected function processAttributeValue(string $key, $value): array
    {
        return [$key, $value];
    }
}
