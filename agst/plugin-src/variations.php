<?php
if(!defined('ABSPATH'))exit;
final class AGST_Variations {
 static function configuration($p){return ['type'=>$p->get_type(),'attributes'=>$p->get_attributes(),'defaults'=>$p->get_default_attributes(),'manage_stock'=>$p->get_manage_stock(),'stock_quantity'=>$p->get_stock_quantity(),'stock_status'=>$p->get_stock_status(),'backorders'=>$p->get_backorders()];}
 static function preflight($r){
  if(empty($r['variants']))return;
  foreach($r['source_keys'] as $key){if($key!==$r['key']&&(AGST_Catalog::prior($key)||get_option('agst_backup_'.$key,false)!==false))throw new RuntimeException('Restore the earlier standalone color test '.$key.' before grouping this family.');}
  foreach($r['variants'] as $v)if(wc_get_product_id_by_sku($v['sku']))throw new RuntimeException('Variation SKU already exists. Resolve it before creating this family.');
  if($r['target_id']){$p=wc_get_product($r['target_id']);if($p&&$p->get_type()!=='simple')throw new RuntimeException('Only the reviewed simple parent can be converted by this family mapping.');}
 }
 static function apply($r,$id){
  if(empty($r['variants']))return;
  $option='agst_backup_'.$r['key'];$b=get_option($option);if(!$b)throw new RuntimeException('Missing family snapshot.');$before=wc_get_product($id);$config=self::configuration($before);$existing=!$b['new'];
  $b['family_configuration']=$config;$b['variation_ids']=[];update_option($option,$b,false);
  $p=new WC_Product_Variable($id);$attrs=[];foreach($p->get_attributes() as $k=>$attribute){$attrs[$k]=clone $attribute;$attrs[$k]->set_variation(false);}$color=new WC_Product_Attribute();$color->set_id(0);$color->set_name('Color');$color->set_options(array_column($r['variants'],'color'));$color->set_visible(true);$color->set_variation(true);$attrs['color']=$color;$p->set_attributes($attrs);$p->set_default_attributes([]);$p->set_manage_stock(false);$p->set_stock_quantity(null);$p->set_regular_price('');$p->set_sale_price('');$p->set_price('');$p->save();
  foreach($r['variants'] as $v){$child=new WC_Product_Variation();$child->set_parent_id($id);$child->set_status('publish');$child->set_attributes(['color'=>$v['color']]);$child->set_sku($v['sku']);$child->set_regular_price($v['price']??'');$child->set_price($v['price']??'');$child->set_manage_stock(false);$child->set_stock_status('instock');
   if($existing&&$v['color']==='Black'){$child->set_manage_stock($config['manage_stock']);$child->set_stock_quantity($config['stock_quantity']);$child->set_stock_status($config['stock_status']);$child->set_backorders($config['backorders']);if($before->get_image_id())$child->set_image_id($before->get_image_id());}
   $child->update_meta_data('_agst_family_key',$r['key']);$vid=$child->save();if(!$vid)throw new RuntimeException('Could not create a variation.');$b['variation_ids'][]=$vid;update_option($option,$b,false);
   if(!$child->get_image_id()&&!empty($v['asset_files'])){$ids=AGST_Catalog::assets(['title'=>$r['title'].' - '.$v['color'],'asset_files'=>[$v['asset_files'][0]]]);if($ids){$child->set_image_id($ids[0]);$child->save();}}
  }
  WC_Product_Variable::sync($id);wc_delete_product_transients($id);
 }
 static function restore($b){
  if(empty($b['family_configuration']))return;$id=(int)$b['id'];$c=$b['family_configuration'];
  $ids=$b['variation_ids']??[];$extra=get_posts(['post_type'=>'product_variation','post_status'=>'any','post_parent'=>$id,'numberposts'=>-1,'fields'=>'ids','meta_key'=>'_agst_family_key']);$ids=array_unique(array_merge($ids,$extra));
  foreach($ids as $vid){$v=wc_get_product($vid);if(!$v||$v->get_parent_id()!==$id)continue;$v->set_sku('');$v->set_status('trash');$v->save();}
  $p=$c['type']==='simple'?new WC_Product_Simple($id):new WC_Product_Variable($id);$p->set_attributes($c['attributes']);$p->set_default_attributes($c['defaults']);$p->set_manage_stock($c['manage_stock']);$p->set_stock_quantity($c['stock_quantity']);$p->set_stock_status($c['stock_status']);$p->set_backorders($c['backorders']);$p->save();
 }
}
