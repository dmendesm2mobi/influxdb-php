<?php

namespace InfluxDB;

use InfluxDB\Database\Exception as DatabaseException;
use UnexpectedValueException;

/**
 * Class Point
 *
 * @package InfluxDB
 */
class Point
{
    /**
     * @var string
     */
    protected $measurement;

    /**
     * @var array
     */
    protected $tags = [];

    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @var string
     */
    protected $timestamp;

    /**
     * The timestamp is optional. If you do not specify a timestamp the server’s
     * local timestamp will be used
     *
     * @param  string $measurement
     * @param  float  $value
     * @param  array  $tags
     * @param  array  $additionalFields
     * @param  int    $timestamp
     * @throws DatabaseException
     */
    public function __construct(
        $measurement,
        $value = null,
        array $tags = array(),
        array $additionalFields = array(),
        $timestamp = null
    ) {
        if (empty($measurement)) {
            throw new DatabaseException('Invalid measurement name provided');
        }

        $this->measurement = (string) $measurement;
        $this->setTags($tags);
        $fields = $additionalFields;

        if ($value !== null) {
            $fields['value'] = $value;
        }

        $this->setFields($fields);

        if ($timestamp && !$this->isValidTimeStamp($timestamp)) {
            throw new DatabaseException(sprintf('%s is not a valid timestamp', $timestamp));
        }

        $this->timestamp = $timestamp;
    }

    /**
     * @see: https://influxdb.com/docs/v0.9/concepts/reading_and_writing_data.html
     *
     * Should return this format
     * 'cpu_load_short,host=server01,region=us-west value=0.64 1434055562000000000'
     */
    public function __toString()
    {
        $string = $this->measurement;

        if (count($this->tags) > 0) {
            $string .=  ',' . $this->arrayToString($this->escapeCharacters($this->tags));
        }

        $string .= ' ' . $this->arrayToString($this->escapeCharacters($this->fields));

        if ($this->timestamp) {
            $string .= ' '.$this->timestamp;
        }

        return $string;
    }

    /**
     * @return string
     */
    public function getMeasurement()
    {
        return $this->measurement;
    }

    /**
     * @param string $measurement
     */
    public function setMeasurement($measurement)
    {
        $this->measurement = $measurement;
    }

    /**
     * @return array
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param array $tags
     */
    public function setTags($tags)
    {
        $tags = array_filter($tags, function($value){ return $value !== NULL; });

        foreach ($tags as &$tag) {
            if ($tag === '') {
                $tag = '""';
            } elseif (is_string($tag)) {
                $tag = $this->addSlashes($tag);
            } elseif (is_bool($tag)) {
                $tag = ($tag ? 'true' : 'false');
            } elseif (is_array($tag)) {
                throw new UnexpectedValueException('Tried sending array value to InfluxDB');
            }
        }

        $this->tags = $tags;
    }

    /**
     * @param array $tags Array of additional tags
     */
    public function addTags($tags)
    {
        $tags = array_filter($tags, function($value){ return $value !== NULL; });

        foreach ($tags as &$tag) {
            if ($tag === '') {
                $tag = '""';
            } elseif (is_string($tag)) {
                $tag = $this->addSlashes($tag);
            } elseif (is_bool($tag)) {
                $tag = ($tag ? 'true' : 'false');
            } elseif (is_array($tag)) {
                throw new UnexpectedValueException('Tried sending array value to InfluxDB');
            }
        }

        $this->tags = array_merge($this->tags, $tags);
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param array $fields
     */
    public function setFields($fields)
    {
        $fields = array_filter($fields, function($value){ return $value !== NULL; });

        foreach ($fields as &$field) {
            if (is_int($field)) {
                $field = sprintf('%di', $field);
            } elseif (is_string($field)) {
                $field = $this->escapeFieldValue($field);
            } elseif (is_bool($field)) {
                $field = ($field ? 'true' : 'false');
            } elseif (is_array($field)) {
                throw new UnexpectedValueException('Tried sending array value to InfluxDB');
            }
        }

        $this->fields = $fields;
    }

    /**
     * @param array $fields Set of fields
     */
    public function addFields($fields)
    {
        $fields = array_filter($fields, function($value){ return $value !== NULL; });

        foreach ($fields as &$field) {
            if (is_int($field)) {
                $field = sprintf('%di', $field);
            } elseif (is_string($field)) {
                $field = $this->escapeFieldValue($field);
            } elseif (is_bool($field)) {
                $field = ($field ? 'true' : 'false');
            } elseif (is_array($field)) {
                throw new UnexpectedValueException('Tried sending array value to InfluxDB');
            }
        }

        $this->fields = array_merge($this->fields, $fields);
    }

    /**
     * @return string
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @param string $timestamp
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;
    }

    /**
     * Escapes invalid characters in both the array key and optionally the array value
     *
     * @param array $arr
     * @return array
     */
    private function escapeCharacters(array $arr)
    {
        $returnArr = [];

        foreach ($arr as $key => $value) {
            $returnArr[$this->addSlashes($key)] = $value;
        }

        return $returnArr;
    }

    /*
     * Returns string double-quoted and double-quotes escaped per Influx write protocol syntax
     *
     * @param string $value
     * @return string
     */
    private function escapeFieldValue($value)
    {
        $escapedValue = addcslashes($value, '"\\');
        return sprintf('"%s"', $escapedValue);
    }

    /**
     * Returns strings with space, comma, or equals sign characters backslashed per Influx write protocol syntax
     *
     * @param string $value
     * @return string
     */
    private function addSlashes($value)
    {
        return addcslashes($value, ' ,=');
    }

    /**
     * @param  array $arr
     * @return string
     */
    private function arrayToString(array $arr)
    {
        $strParts = [];

        $origLocale = setlocale(LC_NUMERIC, 0);
        // Use the POSIX locale which defines the `decimal_point` separator as `.`
        setlocale(LC_NUMERIC, 'POSIX');
        foreach ($arr as $key => $value) {
            $strParts[] = sprintf('%s=%s', $key, $value);
        }
        setlocale(LC_NUMERIC, $origLocale);

        return implode(',', $strParts);
    }

    /**
     * @param  int $timestamp
     * @return bool
     */
    private function isValidTimeStamp($timestamp)
    {
        // if the code is run on a 32bit system, loosely check if the timestamp is a valid numeric
        if (PHP_INT_SIZE === 4 && is_numeric($timestamp)) {
            return true;
        }

        if (!is_numeric($timestamp)) {
            return false;
        }

        if ((int)$timestamp != $timestamp) {
            return false;
        }

        if (!($timestamp <= PHP_INT_MAX && $timestamp >= ~PHP_INT_MAX)) {
            return false;
        }

        return true;
    }
}
