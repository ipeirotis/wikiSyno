<?php

class Application
{

  static function doConnect()
  {
    mysql_connect(DB_HOST, DB_USER, DB_PASS);
    @mysql_select_db(DB_CATALOG) or die("Unable to select database");
    $query = "SET NAMES 'utf8'";
    mysql_query($query);
  }

  static function doClose()
  {
    mysql_close();
  }

  static function moveValueByIndex(array $array, $from = null, $to = null)
  {
    if (null === $from) {
      $from = count($array) - 1;
    }

    if (!isset($array[$from])) {
      throw new Exception("Offset $from does not exist");
    }

    if (array_keys($array) != range(0, count($array) - 1)) {
      throw new Exception("Invalid array keys");
    }

    $value = $array[$from];
    unset($array[$from]);

    if (null === $to) {
      array_push($array, $value);
    } else {
      $tail = array_splice($array, $to);
      array_push($array, $value);
      $array = array_merge($array, $tail);
    }

    return $array;
  }
  
  static function getDisambiguationLinks($id = null)
  {
    if (!$id) {
      return null;
    }
    self::doConnect();
    
    $query = "SELECT * FROM pagelinks WHERE pl_namespace = 0 AND  pl_from = '" . $id . "'";

    $result = mysql_query($query);
    
    while ($row = mysql_fetch_assoc($result)) {
      $data[] = str_replace('_', ' ', $row['pl_title']);
    }
    self::doClose();
    
    if (!empty($data)) {
      return $data;
    }
    return null;
  }

  static function checkDisambiguation($key = null)
  {
    $out = 0;
    if (!$key) {
      return;
    }
    $key = str_replace(' ', '_', $key);
//    die($key);
    $data = array();
    
    self::doConnect();
    
    $query = "SELECT categorylinks.cl_to FROM page JOIN categorylinks ON categorylinks.cl_from = page.page_id WHERE page.page_namespace = 0 AND page.page_title = '" . $key . "'";

    $result = mysql_query($query);
    
    while ($row = mysql_fetch_assoc($result)) {
      $data[] = $row['cl_to'];
    }
    
    self::doClose();
//    if (!empty($data)) {
//      echo $key;
//      var_dump($data);
//      die();
//    }
    foreach ($data as $v => $category) {
      if ($category == 'Disambiguation_pages') {
        $out = 1;
      }
      
      if ($category == 'Unprintworthy_redirects') {
        $out = 2;
      }
    }
    return $out;
  }
  
  static function checkDisambiguations($keys = array())
  {
    $out = 0;
    if (!$keys || empty($keys)) {
      return;
    }
    foreach ($keys as $key => $value){
      $keys[$key] =  (string) str_replace(' ', '_', $value);
    }
//    die($key);
    $data = array();
    
    self::doConnect();
    $k = implode("', '", $keys);
    $query = "SELECT page.page_title as page, GROUP_CONCAT(categorylinks.cl_to) as categories FROM page JOIN categorylinks ON categorylinks.cl_from = page.page_id WHERE page.page_namespace = 0 AND page.page_title IN ('" . $k . "') GROUP BY page.page_title";
    
//    die($query);
    $results = mysql_query($query);

    while ($row = mysql_fetch_assoc($results)) {
      $data[$row['page']] = explode(',', $row['categories']);
    }
    
    self::doClose();
   
    foreach ($data as $d => $categories) {
      if (in_array('Unprintworthy_redirects', $categories)) {
        continue;
      } 
      if (!in_array('Disambiguation_pages', $categories)) {
        unset($data[$d]);
      } 
    }
//    if (!empty($data)) {
//      var_dump($data);
//      die();
//    }
    return $data;
  }
  
  static function getSynonyms($key = null)
  {
    self::doConnect();
    $temp_synoms = array();
    $synoms = array();
    $data = array();
    $data2 = array();
    if ($key) {

//      $key = ucwords($key);
      $key = str_replace(' ', '_', $key);

//      $query = sprintf("SELECT * FROM page_relation WHERE (stitle = '%s' OR ttitle = '%s')  ", $key, $key);
      $query = sprintf("SELECT * FROM page_relation WHERE (stitle = '%s' OR ttitle = '%s') AND (snamespace = 0) AND (tnamespace = 0 OR tnamespace = 14)  ", $key, $key);
      $result = mysql_query($query);

      while ($row = mysql_fetch_assoc($result)) {
        $data[] = $row;
      }
    }
    self::doClose();

    $new_bpages = array();
    foreach ($data as $d) {
      $new_bpages[] = $d['tid'];
      if (!in_array(str_replace('_', ' ', $d['stitle']), $temp_synoms)) {
        $temp_synoms[] = str_replace('_', ' ', $d['stitle']);
        $synoms[] = array(
          'id' => $d['sid'],
          'term' => str_replace('_', ' ', $d['stitle']),
          'ns' => $d['snamespace'],
          'is_primary' => 0,
        );
      }
      if (!in_array(str_replace('_', ' ', $d['ttitle']), $temp_synoms)) {
        $temp_synoms[] = str_replace('_', ' ', $d['ttitle']);
        $synoms[] = array(
          'id' => $d['tid'],
          'term' => str_replace('_', ' ', $d['ttitle']),
          'ns' => $d['tnamespace'],
          'is_primary' => 1,
        );
      }
    }
    if (!empty($new_bpages)) {
      self::doConnect();
      $query2 = "SELECT * FROM page_relation WHERE tid IN (" . implode(',', $new_bpages) . ")";
      $result2 = mysql_query($query2);

      while ($row2 = mysql_fetch_assoc($result2)) {
        $data2[] = $row2;
        if (!in_array(str_replace('_', ' ', $row2['stitle']), $temp_synoms)) {
          $temp_synoms[] = str_replace('_', ' ', $row2['stitle']);
          $synoms[] = array(
            'id' => $row2['sid'],
            'term' => str_replace('_', ' ', $row2['stitle']),
            'ns' => $row2['snamespace'],
            'is_primary' => 0,
          );
        }
        if (!in_array(str_replace('_', ' ', $row2['ttitle']), $temp_synoms)) {
          $temp_synoms[] = str_replace('_', ' ', $row2['ttitle']);
          $synoms[] = array(
            'id' => $row2['tid'],
            'term' => str_replace('_', ' ', $row2['ttitle']),
            'ns' => $row2['tnamespace'],
            'is_primary' => 1,
          );
        }
      }
      self::doClose();
    }

//    if ((in_array($key, $data) || in_array($key, $data2)) && !in_array($key, $synoms)) {
//      array_unshift($synoms, array(
//        'id' => null,
//        'term' => str_replace('_', ' ', $key),
//        'ns' => -100,
//        'is_primary' => 1,
//        )
//      );
//    }
    $disambiguations_check = array();
    $disambiguations = array();
    foreach ($synoms as $key => $synom) {
      $disambiguations_check[] = $synom['term'];
//      $r = self::checkDisambiguation($synom['term']);
//      if ($r == 1) {
//        $synoms[$key]['is_primary'] = 2;
//        $newSynoms = self::getDisambiguationLinks($synoms[$key]['id']);
//        $disambiguations[$synom['term']] = $newSynoms;
//        unset($synoms[$key]);
//      }
//      if ($r == 2) {
//        unset($synoms[$key]);
//      }
      if ($synom['is_primary'] == 1) {
        $synoms = self::moveValueByIndex($synoms, $key, 0);
      }
    }
    $disambigs = self::checkDisambiguations($disambiguations_check);
    
    foreach ($synoms as $key => $synom) {
      if(array_key_exists(str_replace(' ', '_', $synom['term']), $disambigs)) {
        $newSynoms = self::getDisambiguationLinks($synoms[$key]['id']);
        if($newSynoms && !empty($newSynoms)){
          $disambiguations[$synom['term']] = $newSynoms;
        }
        unset($synoms[$key]);
      }
    }
    $out['synoms'] = $synoms;
    if (!empty($disambiguations)) {
      $out['disambiguations'] = $disambiguations;
    }
    return $out;
  }

}