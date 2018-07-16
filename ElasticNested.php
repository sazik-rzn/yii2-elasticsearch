<?php

namespace sazik\elastic;

class ElasticNested {

    private $attributes = [];

    public function __get($name) {
        return (isset($this->attributes[$name])) ? $this->attributes[$name] : false;
    }

    public function __set($name, $value) {
        $this->attributes[$name] = $value;
    }
    
    public function load($attrs){
        $this->attributes = $attrs;
    }

}
