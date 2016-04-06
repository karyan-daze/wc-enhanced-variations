<?php
/**
 * Created by PhpStorm.
 * User: jean
 * Date: 2/19/2016
 * Time: 11:29 AM
 */

namespace Shopboostr;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once(__DIR__ . "/../wp-core/class/Enqueue_helper.php");
require_once(__DIR__ . "/../wp-core/sb_constants.php");




class WC_enhanced_attributes
{
    /**
     * Bootstraps the class and hooks required actions & filters.
     *
     */

    const PAGE_NAME = "SBAttributesManagement";

    private static $table_name;

    private static $required_scripts =
        array("personal" => [array("name"=>"attr_bulk_edit","path"=>'admin_includes/js/',"deps"=>array("wpImageImport")),array("name"=>"wpImageImport", "path"=>"admin_includes/js/")],
            "third-party" => [array("name"=>"isteven-multi-select","path"=>"isteven-multiselect/","deps"=>array("angularJS"),"hasCSS"=>true),
                array("name"=>"wcpdf","path"=>"wcpdf/","deps"=>array("jQuery"))]);

    private static $extra_data_def;

    public static function init()
    {
        global $wpdb;
        WC_enhanced_attributes::$table_name = $wpdb->prefix . DB_PREFIX . 'enhanced_attributes';
        register_activation_hook( __DIR__ . "/sb-wc-enhanced-variations.php", __CLASS__ . '::activate' );
        //register_deactivation_hook( __DIR__ . "/sb-wc-enhanced-variations.php", __CLASS__ . '::deactivate' );
        add_action('admin_menu', __CLASS__ . '::admin_menu');
        add_action( 'admin_enqueue_scripts',  __CLASS__ .  '::required_scripts' );
        add_action('wp_ajax_update_attr_bulk_edit', __CLASS__ . '::update_bulk_attributes_data');
    }

    public static function activate(){
        global $wpdb;

        $enhanced_attributes_table = WC_enhanced_attributes::$table_name;

        $charset_collate = $wpdb->get_charset_collate();


        $sql_enhanced_variations_table = "CREATE TABLE IF NOT EXISTS $enhanced_attributes_table(
		attribute_slug CHAR(20),
		json_data text,
		creation_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		modification_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		CONSTRAINT uc_enhanced_attributes_table PRIMARY KEY (attribute_slug)
	) $charset_collate;";


        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_enhanced_variations_table );
    }

    public static function setDataDef($file){
        WC_enhanced_attributes::$extra_data_def = json_decode(file_get_contents($file),true);
    }

    public static function admin_menu() {
        add_options_page('Enhanced attributes management','Enhanced attributes management','manage_options',WC_enhanced_attributes::PAGE_NAME,__CLASS__ . '::attribute_bulk_edit');
    }

    public static function attribute_bulk_edit(){
        if( !current_user_can('manage_options')){
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        include "admin_includes/attribute_bulk_edit.html";
    }

    //TODO FIXME this is common between enhanced-variations and enhanced-attributes
    public static function get_all_data($extraFieldData){
        $extraFieldsValueOrg = json_decode($extraFieldData["json_data"],true);
        $extraFieldsValue = $extraFieldsValueOrg;
        $extraFieldsProp = array();
        forEach(WC_enhanced_attributes::$extra_data_def as $value){
            $extraFieldsProp[$value['name']] = $value;
        }
        forEach($extraFieldsValue as $key=>$value){
            if(array_key_exists($key,$extraFieldsProp)){
                $extraFieldsValue[$key] = array("value"=>$value);
                if($extraFieldsProp[$key]['type'] == "image"){
                    $extraFieldsValue[$key]["url"] = wp_get_attachment_image_src($value)[0];
                }
            } else {
                unset($extraFieldsValueOrg[$key]);
                self::db_remove_field_enhanced_variation($extraFieldData,$key);
            }
        }
        return $extraFieldsValue;
    }

    public static function get_formatted_attribute($attribute){
        $attribute = (array) $attribute;
        $attribute["name"] = $attribute["attribute_name"];
        $extraFieldsData = WC_enhanced_attributes::db_get_enhanced_attribute($attribute["attribute_name"]);
        if($extraFieldsData){
            $attribute["extraFieldsValue"] = self::get_all_data($extraFieldsData);
        } else {
            $attribute["extraFieldsValue"] = [];
        }
        return $attribute;
    }

    private static function get_wc_attribute_by_name($attr_name){
        $attrs = wc_get_attribute_taxonomies();
        foreach($attrs as $attr){
            if("pa_" . $attr->attribute_name == $attr_name || $attr->attribute_name == $attr_name){
                return $attr;
            }
        }
    }

    public static function get_one_attribute_data($attr_name){
        $_attribute = self::get_wc_attribute_by_name($attr_name);
        return self::get_formatted_attribute($_attribute);
    }

    public static function get_extra_fields_data($attr_name){
        return self::get_one_attribute_data($attr_name)["extraFieldsValue"];
    }

    public static function get_bulk_attributes_data(){
        $product_categories = wc_get_attribute_taxonomies();
        $enhanced_cats = array();

        foreach($product_categories as $cat){
            array_push($enhanced_cats,self::get_formatted_attribute($cat));
        }
        return json_encode($enhanced_cats);
    }

    private static function db_get_enhanced_attribute($attr){
        global $wpdb;
        $return = $wpdb->get_row( "SELECT * FROM " . WC_enhanced_attributes::$table_name . " WHERE attribute_slug='" . $attr  . "'", ARRAY_A);
        return $return;
    }

    private static function db_insert_enhanced_attribute($attr,$json_data){
        global $wpdb;
        $wpdb->insert(
            WC_enhanced_attributes::$table_name,
            array(
                'creation_date' => current_time( 'mysql' ),
                'modification_date' => current_time( 'mysql' ),
                'attribute_slug' => $attr,
                'json_data' => json_encode($json_data)
            )
        );
    }

    private static function _db_update_json_data($oldVar,$json_data){
        global $wpdb;
        $wpdb->update(
            WC_enhanced_attributes::$table_name,
            array(
                "modification_date" => current_time('mysql'),
                "json_data" => json_encode($json_data)
            ),
            array(
                'attribute_slug' => $oldVar["attribute_slug"],
            )
        );
    }

    private static function db_remove_field_enhanced_attribute($oldVar,$fieldToRemove){
        $php_data = json_decode($oldVar["json_data"],true);
        unset($php_data[$fieldToRemove]);
        self::_db_update_json_data($oldVar,$php_data);
    }

    private static function db_update_enhanced_attribute($oldVar, $newVals){
        $php_oldVals = json_decode($oldVar["json_data"],true);
        foreach($newVals as $key => $val){
            $php_oldVals[$key] = $val;
        }
        self::_db_update_json_data($oldVar,$php_oldVals);
    }

    public static function update_bulk_attributes_data(){
        $params = json_decode(file_get_contents('php://input'), true);
        $attribute = $params["attribute"][0];
        $modifiedValues = $params["modifiedValues"];

        $object = WC_enhanced_attributes::db_get_enhanced_attribute($attribute["attribute_name"]);
        if(!$object){
            WC_enhanced_attributes::db_insert_enhanced_attribute($attribute["attribute_name"],$modifiedValues);
        } else {
            WC_enhanced_attributes::db_update_enhanced_attribute($object,$modifiedValues);
        }

        echo(json_encode($modifiedValues));


        exit();
    }

    public static function required_scripts($hook) {
        if ( strpos($hook,WC_enhanced_attributes::PAGE_NAME) === false) {
            return;
        }
        wp_enqueue_media();

        wp_register_script('angularJS', 'https://ajax.googleapis.com/ajax/libs/angularjs/1.4.7/angular.min.js',array( 'jquery' ));


        foreach(WC_enhanced_attributes::$required_scripts["third-party"] as $rs){
            Enqueue_helper::add_third_script($rs,__FILE__);
        }

        $localize = array("name" => 'wpAdminInfos',
            "values" => array(
                'admin_includes' => plugins_url() . Enqueue_helper::get_plugin_dir(__FILE__) . '/admin_includes/',
                'attributesList' =>  WC_enhanced_attributes::get_bulk_attributes_data(),
                'extraDataDef' => json_encode(WC_enhanced_attributes::$extra_data_def)
            ));
        foreach(WC_enhanced_attributes::$required_scripts["personal"] as $rs){
            Enqueue_helper::add_personal_script($rs, __FILE__, $localize);
        }
    }
}
WC_enhanced_attributes::init();