<?php
if(!defined('ABSPATH'))exit;
if(class_exists('AGST_ShopFront')&&AGST_ShopFront::active()){get_header();AGST_ShopFront::render();get_footer();return;}
get_header();
while(have_posts()):the_post();
 global $product;$product=wc_get_product(get_the_ID());
 if(post_password_required()){echo get_the_password_form();continue;}
 if(!$product)continue;
 AGST_Storefront::render(AGST_Storefront::model($product));
 if(function_exists('WC')&&isset(WC()->structured_data))WC()->structured_data->generate_product_data($product);
endwhile;
get_footer();
