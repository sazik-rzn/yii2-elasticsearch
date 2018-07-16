<?php

namespace sazik\elastic;

class Model {

    public $attributes = [];
    public $gettedNested = [];

    /**
     * Set (update) mappings for this model
     */
    public static function updateMapping() {
        $operator = new Operations(static::className());
        return $operator->mapping();
    }

    public static function getEnabledSitesSQLString() {
        $sites = [];
        foreach (\Yii::$app->elastic->enabledSites as $site) {
            $sites[] = "{$site}=1";
        }
        if (!empty($sites) && count($sites) > 1) {
            return " and (" . implode(" or ", $sites) . ")";
        } elseif (!empty($sites) && count($sites) == 1) {
            return " and {$sites[0]}";
        }
        return "";
    }

    public static function siteFilterredSQL($sql) {
        return preg_replace("/\{FILTER\}/", static::getEnabledSitesSQLString(), $sql);
    }

    public function attributes() {
        return array_keys(static::mapping()[static::type()]['properties']);
    }

    /**
     * Example:
     * return [
     *       static::type() => [
     *           'properties' => [
     *               'name'           => ['type' => 'string'],
     *               'author_name'    => ['type' => 'string'],
     *               'publisher_name' => ['type' => 'string'],
     *               'created_at'     => ['type' => 'long'],
     *               'updated_at'     => ['type' => 'long'],
     *               'status'         => ['type' => 'long'],
     *           ]
     *       ],
     *   ];
     * @return array This model's mapping
     */
    public static function mapping() {
        return [];
    }

    /**
     * Compose field with text to search
     * @return string
     */
    public function searchField() {
        return "";
    }

    public static function fixValues($name, $value) {
        if (isset(static::valueFixtures()[$name]) && isset(static::valueFixtures()[$name][$value])) {
            return static::valueFixtures()[$name][$value];
        }
        return $value;
    }

    public static function valueFixtures() {
        return [
            "date_begin" => [
                "0000-00-00" => "0000-01-01",
                "0000-00-00 00:00:00" => "0000-01-01 00:00:00",
                null => "0000-01-01",
                "null" => "0000-01-01"
            ],
            "date_end" => [
                "0000-00-00" => "2100-01-01",
                "0000-00-00 00:00:00" => "2100-01-01 00:00:00",
                null => "2100-01-01",
                "null" => "2100-01-01"
            ],
            "offer_added" => [
                "0000-00-00" => "0000-01-01",
                "0000-00-00 00:00:00" => "0000-01-01 00:00:00",
                null => "0000-01-01",
                "null" => "0000-01-01"
            ]
        ];
    }

    public function load($attrs) {
        foreach ($attrs as $name => $attr) {
            $this->{$name} = $attr;
        }
    }

    public function deleteByID($id) {
        $position = static::clearFind()->addQuery()->filter_Field("id", $id)->closeQuery()->one();
        if ($position)
            return $position->delete();
        return false;
    }

    public static function getFromIndex($id) {
        return static::clearFind()->addQuery()->filter_Field("id", $id)->closeQuery()->arrayOne();
    }

    public function delete() {
        $operator = new Operations(static::className());
        return $operator->delete($this->id());
    }

    public function __get($name) {
        $result = false;
        if (isset($this->attributes[$name])) {
            $result = $this->attributes[$name];
        } elseif (method_exists($this, 'get_' . $name)) {
            if (isset($this->gettedNested[$name])) {
                return $this->gettedNested[$name];
            }
            $result = $this->{'get_' . $name}();
            $this->gettedNested[$name] = $result;
        }
        return $result;
    }

    public function __set($name, $value) {
        $this->attributes[$name] = $value;
    }

    public static function type() {
        $className = array_reverse(explode("\\", static::class));
        return strtolower($className[0]);
    }

    public function get_type() {
        return static::type();
    }

    public function save() {
        $operator = new Operations(static::className());
        return $operator->save($this)["result"];
    }

    public static function className() {
        return get_called_class();
    }

    public static function indexSettings() {
        return [];
    }

    public function id() {
        return false;
    }

    public static function baseSource() {
        $result = [];
        foreach (static::mapping()[static::type()]['properties'] as $key => $value) {
            if ($value["type"] !== "nested") {
                $result[] = $key;
            }
        }
        return $result;
    }

    /**
     * 
     * @return Finder
     */
    public static function find() {
        return new Finder(static::className());
    }

    /**
     * 
     * @return Finder
     */
    public static function clearFind() {
        return new Finder(static::className());
    }

    public static function search($query, $only_query = false) {
        $search = static::find();
        if (method_exists(new static, "searchFind")) {
            $search = static::searchFind();
        }
        $search->addQuery()->simple_query_string($query)->closeQuery();
        if ($only_query) {
            return $search;
        }
        return $search->all();
    }

}
