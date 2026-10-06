{%- comment -%}
  Aluglobus catalog v30: the shop organised like the website price list.
  /collections/all            -> shop overview (systems with their groups)
  /collections/<system>       -> system page (every group with its products)
  /collections/<system>/<tag> -> one group (products tagged with the group name)
  any other collection        -> plain product grid
  Systems and groups below are generated from the website categories; products carry the
  system and group names as tags, and an "_order:NNNN" tag for the price-list order.
{%- endcomment -%}
{{ 'ag-catalog-v30.css' | asset_url | stylesheet_tag }}
{%- liquid
  assign sys_handles = '@@SYS_HANDLES@@' | split: '|'
  assign sys_titles = '@@SYS_TITLES@@' | split: '|'
  assign sys_leads = '@@SYS_LEADS@@' | split: '|'
  assign sys_groups = '@@SYS_GROUPS@@' | split: '|'
  assign sys_gdescs = '@@SYS_GDESCS@@' | split: '|'

  assign agc_sys = -1
  for h in sys_handles
    if collection.handle == h
      assign agc_sys = forloop.index0
    endif
  endfor
  assign agc_mode = 'grid'
  if collection.handle == 'all'
    assign agc_mode = 'shop'
  elsif agc_sys >= 0
    assign agc_mode = 'system'
    if current_tags.size > 0
      assign agc_mode = 'group'
      assign agc_cur = current_tags.first | handleize
    endif
  endif
  assign agc_shop_url = routes.all_products_collection_url
-%}
{%- paginate collection.products by 250 -%}
{%- comment -%} Price-list order: "_order:NNNN" tag, then the collection order. {%- endcomment -%}
{%- capture agc_keys -%}
  {%- for p in collection.products -%}
    {%- assign k = '0000' -%}
    {%- for t in p.tags -%}{%- if t contains '_order:' -%}{%- assign k = t | remove: '_order:' -%}{%- endif -%}{%- endfor -%}
    {%- assign pos = forloop.index0 | prepend: '0000' | slice: -4, 4 -%}
    {{- k -}}~{{- pos -}}{%- unless forloop.last -%},{%- endunless -%}
  {%- endfor -%}
{%- endcapture -%}
{%- assign agc_order = agc_keys | strip_newlines | remove: ' ' | split: ',' | sort -%}

<main id="agc" class="agc">
  {%- case agc_mode -%}
    {%- when 'shop' -%}
      {%- capture agc_jumps -%}
        {%- for h in sys_handles -%}
          {%- assign si = forloop.index0 -%}
          {%- assign st = sys_titles[si] -%}
          {%- assign n = 0 -%}{%- assign img = nil -%}
          {%- for item in agc_order -%}
            {%- assign idx = item | split: '~' | last | plus: 0 -%}
            {%- assign p = collection.products[idx] -%}
            {%- if p.tags contains st -%}{%- assign n = n | plus: 1 -%}{%- if img == nil and p.featured_image -%}{%- assign img = p.featured_image -%}{%- endif -%}{%- endif -%}
          {%- endfor -%}
          {%- if collections[h].image -%}{%- assign img = collections[h].image -%}{%- endif -%}
          {%- if n > 0 -%}
            <a class="agc-jump" href="#sys-{{ h }}"><span class="agc-jump-img">{%- if img -%}{{ img | image_url: width: 160 | image_tag: loading: 'lazy', alt: '' }}{%- endif -%}</span><span class="agc-jump-text"><strong>{{ st }}</strong><em>{{ n }} products</em></span></a>
          {%- endif -%}
        {%- endfor -%}
      {%- endcapture -%}
      <section class="agc-hero">
        <div class="agc-shell">
          <nav class="agc-crumbs" aria-label="Breadcrumb"><a href="{{ routes.root_url }}">Home</a><span aria-hidden="true">/</span><span aria-current="page">Shop</span></nav>
          <p class="agc-eyebrow">Aluglobus shop &middot; {{ collection.products_count }} products</p>
          <h1>{{ section.settings.shop_heading }}</h1>
          <p class="agc-lead">{{ section.settings.shop_lead }}</p>
          {%- render 'ag-catalog-search' -%}
          <div class="agc-jumps">{{ agc_jumps }}</div>
        </div>
      </section>
      <section class="agc-body"><div class="agc-shell">
        {%- assign agc_n = 0 -%}
        {%- for h in sys_handles -%}
          {%- assign si = forloop.index0 -%}
          {%- assign st = sys_titles[si] -%}
          {%- assign groups = sys_groups[si] | split: '~' -%}
          {%- assign total = 0 -%}
          {%- for item in agc_order -%}
            {%- assign idx = item | split: '~' | last | plus: 0 -%}
            {%- if collection.products[idx].tags contains st -%}{%- assign total = total | plus: 1 -%}{%- endif -%}
          {%- endfor -%}
          {%- if total == 0 -%}{%- continue -%}{%- endif -%}
          {%- assign agc_n = agc_n | plus: 1 -%}
          {%- capture tiles -%}
            {%- assign gi = 0 -%}
            {%- for g in groups -%}
              {%- if g == '-' -%}{%- continue -%}{%- endif -%}
              {%- assign gn = 0 -%}{%- assign gimg = nil -%}
              {%- for item in agc_order -%}
                {%- assign idx = item | split: '~' | last | plus: 0 -%}
                {%- assign p = collection.products[idx] -%}
                {%- if p.tags contains g -%}{%- assign gn = gn | plus: 1 -%}{%- if gimg == nil and p.featured_image -%}{%- assign gimg = p.featured_image -%}{%- endif -%}{%- endif -%}
              {%- endfor -%}
              {%- if gn == 0 -%}{%- continue -%}{%- endif -%}
              {%- assign gi = gi | plus: 1 -%}
              <a class="agc-tile" href="{{ routes.collections_url }}/{{ h }}/{{ g | handleize }}"><span class="agc-tile-img">{%- if gimg -%}{{ gimg | image_url: width: 500 | image_tag: loading: 'lazy', widths: '250, 375, 500', sizes: '(min-width: 1100px) 300px, 50vw', alt: '' }}{%- endif -%}</span><span class="agc-tile-body"><span class="agc-num">{{ gi }}</span><strong>{{ g }}</strong><em>{{ gn }} {% if gn == 1 %}product{% else %}products{% endif %}</em></span></a>
            {%- endfor -%}
          {%- endcapture -%}
          {%- assign tiles = tiles | strip -%}
          <section class="agc-group agc-sys" id="sys-{{ h }}">
            <header class="agc-group-head">
              <div><span class="agc-group-num">{{ agc_n | prepend: '0' | slice: -2, 2 }} &middot; {% if tiles == blank %}{{ total }} products{% else %}{{ gi }} groups{% endif %}</span><h2>{{ st }}</h2><p>{{ sys_leads[si] }}</p></div>
              <a class="agc-group-link agc-sys-all" href="{{ routes.collections_url }}/{{ h }}">Shop all {{ total }} {{ st | downcase }} <span aria-hidden="true">&rarr;</span></a>
            </header>
            {%- if tiles == blank -%}
              <div class="agc-grid">
                {%- for item in agc_order -%}
                  {%- assign idx = item | split: '~' | last | plus: 0 -%}
                  {%- assign p = collection.products[idx] -%}
                  {%- if p.tags contains st -%}{%- render 'ag-catalog-card', product: p -%}{%- endif -%}
                {%- endfor -%}
              </div>
            {%- else -%}
              <div class="agc-tiles">{{ tiles }}</div>
            {%- endif -%}
          </section>
        {%- endfor -%}
      </div></section>

    {%- when 'system' or 'group' -%}
      {%- assign st = sys_titles[agc_sys] -%}
      {%- assign h = sys_handles[agc_sys] -%}
      {%- assign groups = sys_groups[agc_sys] | split: '~' -%}
      {%- assign gdescs = sys_gdescs[agc_sys] | split: '~' -%}
      {%- assign cur_title = '' -%}{%- assign cur_desc = '' -%}
      {%- capture chips -%}
        {%- assign gi = 0 -%}
        {%- for g in groups -%}
          {%- if g == '-' -%}{%- continue -%}{%- endif -%}
          {%- assign gn = 0 -%}
          {%- if agc_mode == 'system' -%}
            {%- for p in collection.products -%}{%- if p.tags contains g -%}{%- assign gn = gn | plus: 1 -%}{%- endif -%}{%- endfor -%}
            {%- if gn == 0 -%}{%- continue -%}{%- endif -%}
          {%- endif -%}
          {%- assign gi = gi | plus: 1 -%}
          {%- assign gh = g | handleize -%}
          {%- if agc_mode == 'group' and gh == agc_cur -%}{%- assign cur_title = g -%}{%- assign cur_desc = gdescs[forloop.index0] -%}{%- endif -%}
          <a href="{% if agc_mode == 'group' %}{{ routes.collections_url }}/{{ h }}/{{ gh }}{% else %}#{{ gh }}{% endif %}"{% if agc_mode == 'group' and gh == agc_cur %} aria-current="page"{% endif %}><span class="agc-num">{{ gi }}</span>{{ g }}{% if gn > 0 %}<em>{{ gn }}</em>{% endif %}</a>
        {%- endfor -%}
      {%- endcapture -%}
      {%- assign chips = chips | strip -%}
      <section class="agc-hero agc-hero-compact">
        <div class="agc-shell">
          <nav class="agc-crumbs" aria-label="Breadcrumb"><a href="{{ routes.root_url }}">Home</a><span aria-hidden="true">/</span><a href="{{ agc_shop_url }}">Shop</a><span aria-hidden="true">/</span>{%- if agc_mode == 'group' -%}<a href="{{ collection.url }}">{{ st }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ cur_title | default: current_tags.first }}</span>{%- else -%}<span aria-current="page">{{ st }}</span>{%- endif -%}</nav>
          {%- render 'ag-catalog-systems', sys_handles: sys_handles, sys_titles: sys_titles, current: h -%}
          {%- if agc_mode == 'group' -%}
            <p class="agc-eyebrow">{{ st }}</p>
            <h1>{{ cur_title | default: current_tags.first }}</h1>
            <p class="agc-lead">{% if cur_desc != blank and cur_desc != '-' %}{{ cur_desc }} &middot; {% endif %}Part of our {{ st }} range.</p>
          {%- else -%}
            <p class="agc-eyebrow">{% if chips != blank %}Price list &middot; {{ gi }} groups{% else %}{{ collection.products_count }} products{% endif %}</p>
            <h1>{% if collection.handle == 'pergola' %}Pergola &amp; Patio Cover Kits{% else %}{{ st }}{% endif %}</h1>
            <p class="agc-lead">{{ sys_leads[agc_sys] }}</p>
          {%- endif -%}
        </div>
      </section>
      {%- if chips != blank -%}
        <nav class="agc-chips" aria-label="{{ st }} groups"><div class="agc-shell"><div class="agc-chips-row">{{ chips }}</div></div></nav>
      {%- endif -%}
      <section class="agc-body"><div class="agc-shell">
        {%- if agc_mode == 'group' or chips == blank -%}
          <div class="agc-grid">
            {%- for item in agc_order -%}
              {%- assign idx = item | split: '~' | last | plus: 0 -%}
              {%- render 'ag-catalog-card', product: collection.products[idx] -%}
            {%- endfor -%}
          </div>
          {%- if collection.products.size == 0 -%}<p class="agc-empty">There are no products in this group right now. <a href="{{ agc_shop_url }}">Back to the shop</a></p>{%- endif -%}
        {%- else -%}
          {%- assign gi = 0 -%}
          {%- for g in groups -%}
            {%- if g == '-' -%}{%- continue -%}{%- endif -%}
            {%- assign gn = 0 -%}
            {%- for p in collection.products -%}{%- if p.tags contains g -%}{%- assign gn = gn | plus: 1 -%}{%- endif -%}{%- endfor -%}
            {%- if gn == 0 -%}{%- continue -%}{%- endif -%}
            {%- assign gi = gi | plus: 1 -%}
            {%- assign gd = gdescs[forloop.index0] -%}
            <section class="agc-group" id="{{ g | handleize }}">
              <header class="agc-group-head">
                <div><span class="agc-group-num">{{ gi | prepend: '0' | slice: -2, 2 }}</span><h2>{{ g }}</h2>{% if gd != blank and gd != '-' %}<p>{{ gd }}</p>{% endif %}</div>
                <a class="agc-group-link" href="{{ collection.url }}/{{ g | handleize }}">{{ gn }} products <span aria-hidden="true">&rarr;</span></a>
              </header>
              <div class="agc-grid">
                {%- for item in agc_order -%}
                  {%- assign idx = item | split: '~' | last | plus: 0 -%}
                  {%- assign p = collection.products[idx] -%}
                  {%- if p.tags contains g -%}{%- render 'ag-catalog-card', product: p -%}{%- endif -%}
                {%- endfor -%}
              </div>
            </section>
          {%- endfor -%}
        {%- endif -%}
      </div></section>
      {%- if agc_mode == 'system' and collection.description != blank -%}
        <section class="agc-about"><div class="agc-shell"><details><summary>About {{ st }}</summary><div class="agc-about-body">{{ collection.description }}</div></details></div></section>
      {%- endif -%}

    {%- else -%}
      <section class="agc-hero agc-hero-compact">
        <div class="agc-shell">
          <nav class="agc-crumbs" aria-label="Breadcrumb"><a href="{{ routes.root_url }}">Home</a><span aria-hidden="true">/</span><a href="{{ agc_shop_url }}">Shop</a><span aria-hidden="true">/</span><span aria-current="page">{{ collection.title | escape }}</span></nav>
          {%- render 'ag-catalog-systems', sys_handles: sys_handles, sys_titles: sys_titles, current: '' -%}
          <p class="agc-eyebrow">{{ collection.products_count }} products</p>
          <h1>{{ collection.title | escape }}</h1>
          {%- if collection.description != blank -%}<div class="agc-lead">{{ collection.description | strip_html | truncate: 220 }}</div>{%- endif -%}
        </div>
      </section>
      <section class="agc-body"><div class="agc-shell">
        <div class="agc-grid">
          {%- for item in agc_order -%}
            {%- assign idx = item | split: '~' | last | plus: 0 -%}
            {%- render 'ag-catalog-card', product: collection.products[idx] -%}
          {%- endfor -%}
        </div>
        {%- if collection.products.size == 0 -%}<p class="agc-empty">There are no products in this collection right now. <a href="{{ agc_shop_url }}">Back to the shop</a></p>{%- endif -%}
        {%- if paginate.pages > 1 -%}{%- render 'pagination', paginate: paginate -%}{%- endif -%}
      </div></section>
  {%- endcase -%}

  <section class="agc-help">
    <div><p class="agc-eyebrow">Need a hand?</p><h2>Not sure which parts you need?</h2><p>Send us your opening sizes and we will put the complete material list together for you.</p></div>
    <div class="agc-help-actions"><a class="agc-btn agc-btn-accent" href="{{ section.settings.quote_url | default: '/pages/contact' }}">Get an online quote</a><a class="agc-btn agc-btn-line" href="{{ section.settings.contact_url | default: '/pages/contact' }}">Talk to a specialist</a></div>
  </section>
</main>
{%- endpaginate -%}

{% schema %}
{
  "name": "Aluglobus catalog",
  "tag": "section",
  "class": "section ag-catalog-section",
  "settings": [
    {"type": "text", "id": "shop_heading", "label": "Shop page heading", "default": "Everything on our current price list."},
    {"type": "textarea", "id": "shop_lead", "label": "Shop page intro", "default": "Choose your system, then the part you need. Prices shown are base prices — bundle and contractor discounts are available."},
    {"type": "url", "id": "quote_url", "label": "Quote button link"},
    {"type": "url", "id": "contact_url", "label": "Specialist button link"}
  ],
  "presets": [{"name": "Aluglobus catalog"}]
}
{% endschema %}
