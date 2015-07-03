<?php 

interface map_provider {

	/**
	 * Given coordinates return an address
	 *
	 * @param string $lat latitude
	 * @param string $long longitude
	 * @param string $zoom the map level of detail
	 * @param string $alt altitude (optional)
	 * @return array microformats2 address elements in an array
	 */
	public function reverse_lookup($lat, $lon, $zoom=18, $alt = NULL);

  /**
   * Given coordinates return HTML code for a map
   *
   * @param string $lat latitude
   * @param string $long longitude
   * @param string $height
   * @param string $width
   * @param string $zoom the map level of detail
   * @return string URL of map 
   */
  public function get_the_map_url($lat, $lon, $height=300, $width=300, $zoom=14);


  /**
   * Given coordinates return HTML code for a map
   *
   * @param string $lat latitude
   * @param string $long longitude
	 * @param string $height
	 * @param string $width
   * @param string $zoom the map level of detail
   * @return string HTML marked up map
   */
	public function get_the_map($lat, $lon, $height=300, $width=300, $zoom=14);

  /**
   * Given coordinates echo the output of get_the_map
   *
   * @param string $lat latitude  
   * @param string $long longitude
   * @param string $height
   * @param string $width
   * @param string $zoom the map level of detail
   * @return echos the output
   */
	public function the_map($lat, $lon, $height=300, $width=300, $zoom=14);
}
