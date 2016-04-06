<?php
/**
 * Created by PhpStorm.
 * User: jean
 * Date: 2/19/2016
 * Time: 11:29 AM
 */

/*
Plugin Name: Shopboostr Enhanced Variations Manager
Plugin URI: https://shopboostr.de
Version: 1.0.0
Author: Shopboostr UG
Author URI: https://shopboostr.de
*/

namespace Shopboostr;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once(__DIR__ . "/../wp-core/class/Enqueue_helper.php");
require_once(__DIR__ . "/../wp-core/sb_constants.php");
require_once("sb-wc-enhanced-attributes.php");




class WC_enhanced_variations
{
    /**
     * Bootstraps the class and hooks required actions & filters.
     *
     */

    const PAGE_NAME = "SBVariationsAttributesManagement";

    private static $table_name;

    private static $required_scripts =
            array("personal" => [array("name"=>"var_bulk_edit","path"=>'admin_includes/js/',"deps"=>array("wpImageImport")),array("name"=>"wpImageImport","path"=>"admin_includes/js/")],
                "third-party" => [array("name"=>"isteven-multi-select","path"=>"isteven-multiselect/","deps"=>array("angularJS"),"hasCSS"=>true),
                                array("name"=>"wcpdf","path"=>"wcpdf/","deps"=>array("jQuery"))]);

    private static $extra_data_def;

    public static function init()
    {
        global $wpdb;
        WC_enhanced_variations::$table_name = $wpdb->prefix . DB_PREFIX . 'enhanced_variations';
        register_activation_hook( __FILE__, __CLASS__ . '::activate' );
        //register_deactivation_hook( __FILE__, __CLASS__ . '::deactivate' );
        add_action('admin_menu', __CLASS__ . '::admin_menu');
        add_action( 'admin_enqueue_scripts',  __CLASS__ .  '::required_scripts' );
        add_action('wp_ajax_update_var_bulk_edit', __CLASS__ . '::update_bulk_variations_data');
    }

    public static function activate(){
        global $wpdb;

        $enhanced_variations_table = WC_enhanced_variations::$table_name;
        $wp_posts_table = $wpdb->prefix . "posts";

        $charset_collate = $wpdb->get_charset_collate();


        $sql_enhanced_variations_table = "CREATE TABLE IF NOT EXISTS $enhanced_variations_table(
		product_id bigint(20) unsigned,
		attribute_slug CHAR(20),
		value_slug CHAR(20),
		json_data text,
		creation_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		modification_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		CONSTRAINT uc_enhanced_variations_table PRIMARY KEY (product_id,attribute_slug,value_slug),
		FOREIGN KEY (product_id) REFERENCES $wp_posts_table(ID)
	) $charset_collate;";


        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_enhanced_variations_table );
    }

    public static function setDataDef($file){
        WC_enhanced_variations::$extra_data_def = json_decode(file_get_contents($file),true);
    }

    public static function admin_menu() {
        add_options_page('Enhanced variations management','Enhanced variations management','manage_options',WC_enhanced_variations::PAGE_NAME,__CLASS__ . '::variation_bulk_edit');
    }

    public static function variation_bulk_edit(){
        if( !current_user_can('manage_options')){
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        include "admin_includes/variation_bulk_edit.html";
    }

    public static function get_all_data($extraFieldData){
        $extraFieldsValueOrg = json_decode($extraFieldData["json_data"],true);
        $extraFieldsValue = $extraFieldsValueOrg;
        $extraFieldsProp = array();
        forEach(WC_enhanced_variations::$extra_data_def as $value){
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


    public static function get_one_product_data($product_id){
        $_pf = new \WC_Product_Factory();
        $_product = $_pf->get_product($product_id);
        return self::get_formatted_product($_product);
    }

    private static function get_formatted_product($product){
        $myproduct = array("product"=> $product);
        $myproduct["attributes"] = array();
        forEach($product->get_attributes() as $attr){
            if($attr["is_taxonomy"]==0){
                $values = array_map(function($item){return array("name"=>$item,"slug"=>$item);},explode(" | ",$attr["value"]));
            } else {
                $attr["extraFieldsValue"] = WC_enhanced_attributes::get_extra_fields_data($attr["name"]);
                $attr["values"] = wp_get_post_terms($product->id, $attr['name']);
                forEach($attr["values"] as $key => $value) {
                    $attr["values"][$key] = (array) $value;
                }
            }
            forEach($attr["values"] as $key => $value){
                $extraFieldData = WC_enhanced_variations::db_get_enhanced_varation($product->get_id(),$attr["name"],$value["slug"]);
                if($extraFieldData){
                    $attr["values"][$key]["extraFieldsValue"] = WC_enhanced_variations::get_all_data($extraFieldData);
                } else {
                    $attr["values"][$key]["extraFieldsValue"] = array();
                }
            }
            $myproduct["attributes"][$attr['name']] = $attr;
        }
        $tmpvar = new \WC_Product_Variable($product->id);
        $myproduct["id"] = $product->id;
        $myproduct["name"] = $product->post->post_title;
        $myproduct["variations"] = $tmpvar->get_available_variations();
        return $myproduct;
    }




    public static function get_bulk_variations_data(){
        $args = array('post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => '', 'orderby' => 'rand', 'order' => 'DESC');
        $loop = new \WP_Query($args);
        $all_products = array();
        while ($loop->have_posts()) : $loop->the_post();
            global $product;
            $myproduct = self::get_formatted_product($product);
            array_push($all_products,$myproduct);
        endwhile;
        return json_encode($all_products);
    }

    private static function db_get_enhanced_varation($pid,$attr,$val){
        global $wpdb;
        $return = $wpdb->get_row( "SELECT * FROM " . WC_enhanced_variations::$table_name . " WHERE product_id=" . $pid . " and attribute_slug='" . $attr . "' and value_slug='" . $val . "'", ARRAY_A);
        return $return;
    }

    private static function db_insert_enhanced_variation($pid,$attr,$val,$json_data){
        global $wpdb;
        $wpdb->insert(
            WC_enhanced_variations::$table_name,
            array(
                'creation_date' => current_time( 'mysql' ),
                'modification_date' => current_time( 'mysql' ),
                'product_id' => $pid,
                'attribute_slug' => $attr,
                'value_slug' => $val,
                'json_data' => json_encode($json_data)
            )
        );
    }

    private static function _db_update_json_data($oldVar,$json_data){
        global $wpdb;
        $wpdb->update(
            WC_enhanced_variations::$table_name,
            array(
                "modification_date" => current_time('mysql'),
                "json_data" => json_encode($json_data)
            ),
            array(
                'product_id' => $oldVar["product_id"],
                'attribute_slug' => $oldVar["attribute_slug"],
                'value_slug' => $oldVar["value_slug"]
            )
        );
    }

    private static function db_remove_field_enhanced_variation($oldVar,$fieldToRemove){
        $php_data = json_decode($oldVar["json_data"],true);
        unset($php_data[$fieldToRemove]);
        self::_db_update_json_data($oldVar,$php_data);
    }

    private static function db_update_enhanced_variation($oldVar, $newVals){
        $php_oldVals = json_decode($oldVar["json_data"],true);
        foreach($newVals as $key => $val){
            $php_oldVals[$key] = $val;
        }
        self::_db_update_json_data($oldVar,$php_oldVals);
    }

    public static function update_bulk_variations_data(){
        $params = json_decode(file_get_contents('php://input'), true);
        $product = $params["product"][0];
        $attribute = $params["attribute"][0];
        $value = $params["value"][0];
        $modifiedValues = $params["modifiedValues"];
        $_pf = new \WC_Product_Factory();
        $php_product = $_pf->get_product((int)$product["product"]["id"]);
        $variations = $php_product->get_available_variations();

        $object = WC_enhanced_variations::db_get_enhanced_varation($php_product->get_id(),$attribute["name"],$value["slug"]);

        if(array_key_exists("price",$modifiedValues)) {
            $priceDelta = (int) $modifiedValues["price"];
            if($object){
                $oldValues = json_decode($object["json_data"],true);
                if(array_key_exists("price",$oldValues)){
                    $priceDelta -= (int) $oldValues["price"];
                }
            }
            foreach ($variations as $var) {
                $var_attrs = $var['attributes'];
                if ($var_attrs["attribute_" . $attribute["name"]] == $value["slug"]) {
                    $old_price = get_post_meta( $var["variation_id"], '_regular_price', true );
                    update_post_meta($var['variation_id'], "_regular_price", $old_price + $priceDelta);
                }
            }
        }


        if(!$object){
            WC_enhanced_variations::db_insert_enhanced_variation($php_product->get_id(),$attribute["name"],$value["slug"],$modifiedValues);
        } else {
            WC_enhanced_variations::db_update_enhanced_variation($object,$modifiedValues);
        }

        echo(json_encode($modifiedValues));


        exit();
    }

    public static function required_scripts($hook) {
        if ( strpos($hook,WC_enhanced_variations::PAGE_NAME) === false) {
            return;
        }
        wp_enqueue_media();

        wp_register_script('angularJS', 'https://ajax.googleapis.com/ajax/libs/angularjs/1.4.7/angular.min.js',array( 'jquery' ));


        foreach(WC_enhanced_variations::$required_scripts["third-party"] as $rs){
            Enqueue_helper::add_third_script($rs,__FILE__);
        }

        $localize = array("name" => 'wpAdminInfos',
                        "values" => array(
                            'admin_includes' => plugins_url() . Enqueue_helper::get_plugin_dir(__FILE__) . '/admin_includes/',
                            'productList' =>  WC_enhanced_variations::get_bulk_variations_data(),
                            'extraDataDef' => json_encode(WC_enhanced_variations::$extra_data_def)
                        ));
        foreach(WC_enhanced_variations::$required_scripts["personal"] as $rs){
            Enqueue_helper::add_personal_script($rs, __FILE__, $localize);
        }
    }
}
WC_enhanced_variations::init();