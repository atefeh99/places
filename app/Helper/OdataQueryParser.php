<?php


namespace App\Helper;

class OdataQueryParser
{
    const COUNT_KEY = "count";
    const FILTER_KEY = "filter";
    const FORMAT_KEY = "format";
    const ORDER_BY_KEY = "orderby";
    const SELECT_KEY = "select";
    const SKIP_KEY = "skip";
    const TOP_KEY = "top";

    /**
     * @var string
     */
    private static $url = "";

    /**
     * @var string
     */
    private static $query_string = "";

    /**
     * @var array
     */
    private static $query_strings = [];

    /**
     * @var bool
     */
    private static $with_dollar = false;

    /**
     * @var string
     */
    private static $select_key = "";

    /**
     * @var string
     */
    private static $count_key = "";

    /**
     * @var string
     */
    private static $filter_key = "";

    /**
     * @var string
     */
    private static $format_key = "";

    /**
     * @var string
     */
    private static $order_by_key = "";

    /**
     * @var string
     */
    private static $skip_key = "";

    /**
     * @var string
     */
    private static $top_key = "";

    private static $fails=false;

    private static $errors =[];
    public static function parse(string $url, bool $with_dollar = true)
    {
        $output = [];

        static::$url = $url;
        static::$with_dollar = $with_dollar;

        if (static::urlInvalid()) {
            return false;
        }

        static::setQueryStrings();

        static::setQueryParameterKeys();

        if (static::selectQueryParameterIsValid()) {
            $output["select"] = static::getSelectColumns();
        }

        if (static::countQueryParameterIsValid()) {
            $output["count"] = true;
        }

        if (static::topQueryParameterIsValid()) {
            $top = static::getTopValue();

            if (!\is_numeric($top)) {
                static::$fails=true;
                static::$errors[]=['top' =>'top should be an integer'];
            }

            $top = $top;

            if ($top < 0) {
                static::$fails=true;
                static::$errors[]=['top' =>'top should be greater or equal to zero'];
            }

            $output["top"] = (int) $top;
        }

        if (static::skipQueryParameterIsValid()) {
            $skip = static::getSkipValue();

            if (!\is_numeric($skip)) {
                static::$fails=true;
                static::$errors[]=['skip' =>'skip should be an integer'];
            }

            $skip = $skip;

            if ($skip < 0) {
                static::$fails=true;
                static::$errors[]=['skip' =>'skip should be greater or equal to zero'];
            }

            $output["skip"] = (int) $skip;
        }

        if (static::orderByQueryParameterIsValid()) {
            $items = static::getOrderByColumnsAndDirections();

            $order_by = \array_map(function($item) {
                $exploded_item = \explode(" ", $item);

                $exploded_item = array_values(array_filter($exploded_item, function($item) {
                    return $item !== "";
                }));

                $property = $exploded_item[0];
                $direction = isset($exploded_item[1]) ? $exploded_item[1] : "asc";

                if ($direction !== "asc" && $direction !== "desc") {
                    static::$fails=true;
                    static::$errors[]=['order' =>'direction should be either asc or desc'];
                }

                return [
                    "property" => $property,
                    "direction" => $direction
                ];
            }, $items);

            $output["orderBy"] = $order_by;
        }

        if (static::filterQueryParameterIsValid())
        {
            $ands = static::getFilterValue();

            $output["filter"] = $ands;
        }


        return $output;
    }

    private static function urlInvalid(): bool
    {
        return \filter_var(static::$url, FILTER_VALIDATE_URL) === false;
    }

    private static function setQueryStrings(): void
    {
        static::$query_string = static::getQueryString();
        static::$query_strings = static::getQueryStrings();
    }

    private static function getQueryString(): string
    {
        $query_string = \parse_url(static::$url, PHP_URL_QUERY);

        return $query_string === null ? "" : $query_string;
    }

    private static function getQueryStrings(): array
    {
        $result = [];

        if (!empty(static::$query_string)) {
            \parse_str(static::$query_string, $result);
        }

        return $result;
    }

    private static function hasKey(string $key): bool
    {
        return isset(static::$query_strings[$key]);
    }

    private static function selectQueryParameterIsValid(): bool
    {
        return static::hasKey(static::$select_key) && !empty(static::$query_strings[static::$select_key]);
    }

    private static function countQueryParameterIsValid(): bool
    {
        return static::hasKey(static::$count_key) && (bool) trim(static::$query_strings[static::$count_key]) === true;
    }

    private static function topQueryParameterIsValid(): bool
    {
        return static::hasKey(static::$top_key);
    }

    private static function skipQueryParameterIsValid(): bool
    {
        return static::hasKey(static::$skip_key);
    }

    private static function orderByQueryParameterIsValid(): bool
    {
        return static::hasKey(static::$order_by_key) && !empty(static::$query_strings[static::$order_by_key]);
    }

    private static function filterQueryParameterIsValid(): bool
    {
        return static::hasKey(static::$filter_key) && !empty(static::$query_strings[static::$filter_key]);
    }

    private static function getSelectColumns(): array
    {
        return array_map(function($column) {
            return trim($column);
        }, explode(",", static::$query_strings[static::$select_key]));
    }

    private static function getTopValue(): string
    {
        return trim(static::$query_strings[static::$top_key]);
    }

    private static function getSkipValue(): string
    {
        return trim(static::$query_strings[static::$skip_key]);
    }

    private static function getOrderByColumnsAndDirections(): array
    {
        return explode(",", static::$query_strings[static::$order_by_key]);
    }

    private static function getFilterValue(): array
    {
        return array_map(function($and) {
            $items = [];
            $left = null;
            $operator = null;
            $right = null;


            preg_match("/([\w]+)\s+(eq|ne|gt|ge|lt|le|in)\s+([\w',\(\)\s.\-]+)/", $and, $items);
            if ($items) {
                $left = $items[1];
                $operator = static::getFilterOperatorName($items[2]);
                $right = static::getFilterRightValue($operator, $items[3]);
            }

            preg_match('/substringof\([\'"](?\'value\'(\w|\d|\W)*)[\'"], *(?\'column\'(\w|\d|\W)*(\.(\w|\d|\W)+){0,1})\) *eq *true/', $and, $items);
            if ($items) {
                $left = $items['column'];
                $operator = '=';
                $right = $items['value'];
            }


            return [
                "left" => $left,
                "operator" => $operator,
                "right" => $right
            ];
        }, explode("and", static::$query_strings[static::$filter_key]));
    }

    private static function setQueryParameterKeys(): void
    {
        static::$select_key = static::getSelectKey();
        static::$count_key = static::getCountKey();
        static::$filter_key = static::getFilterKey();
        static::$format_key = static::getFormatKey();
        static::$order_by_key = static::getOrderByKey();
        static::$skip_key = static::getSkipKey();
        static::$top_key = static::getTopKey();
    }

    private static function getSelectKey(): string
    {
        return static::$with_dollar ? '$' . static::SELECT_KEY : static::SELECT_KEY;
    }

    private static function getCountKey(): string
    {
        return static::$with_dollar ? '$' . static::COUNT_KEY : static::COUNT_KEY;
    }

    private static function getFilterKey(): string
    {
        return static::$with_dollar ? '$' . static::FILTER_KEY : static::FILTER_KEY;
    }

    private static function getFormatKey(): string
    {
        return static::$with_dollar ? '$' . static::FORMAT_KEY : static::FORMAT_KEY;
    }

    private static function getOrderByKey(): string
    {
        return static::$with_dollar ? '$' . static::ORDER_BY_KEY : static::ORDER_BY_KEY;
    }

    private static function getSkipKey(): string
    {
        return static::$with_dollar ? '$' . static::SKIP_KEY : static::SKIP_KEY;
    }

    private static function getTopKey(): string
    {
        return static::$with_dollar ? '$' . static::TOP_KEY : static::TOP_KEY;
    }

    private static function getFilterOperatorName(string $operator): string
    {
        switch($operator) {
            case $operator === "eq":
                return "=";

            case $operator === "ne":
                return "!=";

            case $operator === "gt":
                return ">";

            case $operator === "ge":
                return ">=";

            case $operator === "lt":
                return "<";

            case $operator === "le":
                return "<=";

            case $operator === "in":
                return "in";

            default:
                return "unknown";
        }
    }

    private static function getFilterRightValue(string $operator, string $value) {
        if ($operator !== "in") {
            if (is_numeric($value)) {
                if ((int) $value != $value) {
                    return (float) $value;
                } else {
                    return (int) $value;
                }
            } else {
                return str_replace("'", "", trim($value));
            }
        } else {
            $value = preg_replace("/^\s*\(|\)\s*$/", "", $value);
            $values = explode(",", $value);

            return array_map(function($value) {
                return static::getFilterRightValue("equal", $value);
            }, $values);
        }
    }
    /**
     * @return array
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * @return bool
     */
    public static function isFails(): bool
    {
        return self::$fails;
    }

}
