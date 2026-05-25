(function($){
  let state={page:1,limit:20,search:'',type:''};
  function loadProducts(){
    $.post(PMA_PM.ajax_url,{action:'pma_pm_list_products',nonce:PMA_PM.nonce,page:state.page,limit:state.limit,search:state.search,type:state.type},function(resp){
      if(!resp.success)return;
      const d=resp.data;
      const rows=d.items.map(p=>`<tr data-id="${p.id}"><td class="drag">☰</td><td>${p.id}</td><td>${p.name}</td><td>${p.type}</td><td contenteditable="true" data-key="price">${p.price||''}</td><td contenteditable="true" data-key="stock">${p.stock??''}</td><td contenteditable="true" data-key="sku">${p.sku||''}</td><td>${p.brand||''}</td><td><a href="#" class="pma-show-variations">${p.variation_count}</a></td></tr>`).join('');
      $('#pma-product-grid tbody').html(rows);
      $('#pma-pagination').text(`Page ${d.page} / ${d.pages} (Total ${d.total})`);
      $('#pma-product-grid tbody').sortable({handle:'.drag',update:function(){
        const rows=[]; $('#pma-product-grid tbody tr').each((i,tr)=>rows.push({id:$(tr).data('id'),menu_order:i}));
        $.post(PMA_PM.ajax_url,{action:'pma_pm_reorder_products',nonce:PMA_PM.nonce,rows:rows});
      }});
    });
  }
  function loadVariations(productId){
    $.post(PMA_PM.ajax_url,{action:'pma_pm_list_variations',nonce:PMA_PM.nonce,product_id:productId,limit:50,page:1},function(resp){
      if(!resp.success)return; const rows=resp.data.items.map(v=>`<tr data-id="${v.id}"><td>${v.size}</td><td>${v.color}</td><td>${v.surface}</td><td>${v.thickness}</td><td contenteditable="true" data-key="sku">${v.sku||''}</td><td contenteditable="true" data-key="price">${v.price||''}</td><td contenteditable="true" data-key="stock">${v.stock??''}</td></tr>`).join('');
      $('#pma-variation-grid').html(`<table class="wp-list-table widefat striped"><thead><tr><th>Size</th><th>Color</th><th>Surface</th><th>Thickness</th><th>SKU</th><th>Price</th><th>Stock</th></tr></thead><tbody>${rows}</tbody></table>`);
    });
  }
  $(document).on('blur','#pma-product-grid [contenteditable="true"]',function(){const $td=$(this),$tr=$td.closest('tr');$.post(PMA_PM.ajax_url,{action:'pma_pm_update_product',nonce:PMA_PM.nonce,id:$tr.data('id'),data:{[$td.data('key')]:$td.text().trim()}});});
  $(document).on('blur','#pma-variation-grid [contenteditable="true"]',function(){const $td=$(this),$tr=$td.closest('tr');$.post(PMA_PM.ajax_url,{action:'pma_pm_update_variation',nonce:PMA_PM.nonce,id:$tr.data('id'),data:{[$td.data('key')]:$td.text().trim()}});});
  $(document).on('click','.pma-show-variations',function(e){e.preventDefault();loadVariations($(this).closest('tr').data('id'));});
  $('#pma-refresh').on('click',()=>{state.search=$('#pma-search').val();state.type=$('#pma-type').val();loadProducts();});
  $('#pma-search,#pma-type').on('change',()=>$('#pma-refresh').trigger('click'));
  $(loadProducts);
})(jQuery);
