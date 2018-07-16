<?php

namespace sazik\elastic;

class BoolQuery {

    private $type;
    private $inner_hits;
    private $finder;
    public $search = false;
    private $bool = [
        "must" => [],
        "must_not" => [],
        "should" => [],
        "range" => []
    ];
    private $exists = [];
    private $range = [];
    private $inner_hits_options = [
        "_source" => true,
        "size" => 100
    ];

    public function __construct($finder = false, $type = false, $inner_hits = false) {
        $this->finder = $finder;
        $this->type = $type;
        $this->inner_hits = $inner_hits;
    }

    public function getQuery() {
        $query = false;
        if ($this->type) {
            $query = [
                "nested" => [
                    "path" => $this->type,
                    "query" => [
                    ]
                ]
            ];
            foreach ($this->bool as $bool => $terms) {
                if (!empty($terms)) {
                    if (!isset($query["nested"]["query"]["bool"])) {
                        $query["nested"]["query"]["bool"] = [];
                    }
                    $query["nested"]["query"]["bool"][$bool] = $terms;
                }
            }
            if ($this->inner_hits && !$this->finder->inner) {
                $this->finder->inner = true;
                $this->finder->setParam("_source", false);
                $this->finder->setParam("size", 10000);
                $query["nested"]["inner_hits"] = $this->inner_hits_options;
            }
            return $query;
        } else {
            $query = [
                "bool" => [
                ]
            ];
            foreach ($this->bool as $bool => $terms) {
                if (!empty($terms)) {
                    $query["bool"][$bool] = $terms;
                }
            }
        }
        return $query;
    }

    /**
     * 
     * @return Finder
     */
    public function closeQuery() {
        $this->finder->storeQuery($this);
        return $this->finder;
    }

    public function inner_hits_sort($sort) {
        if (!isset($this->inner_hits_options["sort"])) {
            $this->inner_hits_options["sort"] = [];
        }
        $this->inner_hits_options["sort"][] = $sort;
        return $this;
    }

    public function filter_Field($field, $values) {
        if (!is_array($values)) {
            $values = [$values];
        }
        $this->bool["filter"][] = [
            "terms" => [
                $field => $values
            ]
        ];
        return $this;
    }

    public function must_Field($field, $values) {
        return $this->filter_Field($field, $values);
    }

    public function must_not_Field($field, $values) {
        if (!is_array($values)) {
            $values = [$values];
        }
        $this->bool["must_not"][] = [
            "terms" => [
                $field => $values
            ]
        ];
        return $this;
    }

    public function should_Field($field, $values) {
        return $this->filter_Field($field, $values);
    }

    public function like_Field($field, $values) {
        if (!is_array($values)) {
            $values = [$values];
        }
        foreach ($values as $value) {
            $this->bool["should"][] = [
                "wildcard" => [
                    $field => $value
                ]
            ];
        }
        return $this;
    }

    public function must_Query($query) {
        $this->bool["must"][] = [
            $query->getQuery()
        ];
        return $this;
    }

    public function must_not_Query($query) {
        $this->bool["must_not"][] = [
            $query->getQuery()
        ];
        return $this;
    }

    public function should_Query($query) {
        $this->bool["should"][] = [
            $query->getQuery()
        ];
        return $this;
    }

    public function match_phrase($field, $query_string) {
        
    }

    public function simple_query_string($query) {
        $this->search = true;
        $this->bool["should"] = [
            "query_string" => [
                "default_field" => "search_field",
                "query" => $query,
                "default_operator" => "AND"
            ]
        ];
        return $this;
    }

}
