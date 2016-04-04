=== Plugin Name ===
Contributors: Jean Duthon
Tags: Woocommerce, variation, shopboostr
Requires at least: 3.0.1
Tested up to: 3.4
Stable tag: 4.3


This plugin aims to simplify working with woocommerce by adding extra field to attribute value in products (for example a per value per product pricing possibility.

== Description ==
This plugin aims to simplify working with woocommerce by adding extra field to attribute value in products (for example a per value per product pricing possibility.

== Installation ==

This plugin has been designed for a bedrock installation of wordpress to take benefits of composer.

If you need to work on this plugin, please just git clone it to your plugins directory and activate it in the WP plugins menu.

If you only need to use it, please use composer to install it.

Then to actually use it please include the file WC_enhanced_variations::setDataDef(pathToYourJsonExtraFieldDescription).

You can have a look at an example json @root dir (file value_extras_desc_example.json).

Possible types for now are :
- number
- text => long text
- image

Some extra field comes with special power, the list so far is :
- The name "price" will automagically update prices for all variations of the product that has the value for that attribute

== Usage ==
To retrieve a product by product id you can use the function :
\Shopboostr\WC_enhanced_variations::get_one_product_data(product_id)
It returns the product with product_id in the following form :
{
 product: WC_Product_Variable Object,
 attributes: {
    ~attrName:{
        name: ~attrName,
        values: [WooCommerceVariations]
    }...
 },
 id: ~productId,
 name: ~productName,
 variations: [WooCommerceVariations]
}

== Frequently Asked Questions ==


== Screenshots ==


== Changelog ==

== Upgrade Notice ==