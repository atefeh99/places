<?php
namespace App\CustomClasses\DBobject;
class Place
{
    public $province;
    public $county;
    public $district;
    public $city;
    public $region;
    public $neighborhood;
    public $village;
    public $name;
    public $address;
    public $type;
    public $subcategory;
    public $location;
    public $distance;

    /**
     * Place constructor.
     * @param $province
     * @param $county
     * @param $district
     * @param $city
     * @param $region
     * @param $neighborhood
     * @param $village
     * @param $name
     * @param $address
     * @param $type
     * @param $subcategory
     * @param $location
     * @param $distance
     * @param $geometry
     * return place info
     */

    public function __construct($province, $county, $district, $city, $region, $neighborhood, $village, $name, $address,
                                $type, $subcategory, $location, $distance )
    {
        $this->province = $province;
        $this->county = $county;
        $this->district = $district;
        $this->city = $city;
        $this->region = $region;
        $this->neighborhood = $neighborhood;
        $this->village = $village;
        $this->name = $name;
        $this->address = $address;
        $this->type = $type;
        $this->subcategory = $subcategory;
        $this->location = $location;
//        $this->geometry = $geometry;
        $this->distance = $distance;
    }
}
