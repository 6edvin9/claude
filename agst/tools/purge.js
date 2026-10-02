async () => { const h = await (await fetch('/wp-admin/admin.php?page=wpo_cache')).text();
 const before = (h.match(/Cache size: [^<]+/) || [''])[0];
 const p = h.match(/_wpo_purge=([0-9a-f]{10})/); const r1 = await fetch('/wp-admin/admin.php?page=wpo_cache&_wpo_purge=' + p[1]);
 const h2 = await r1.text(); const after = (h2.match(/Cache size: [^<]+/) || [''])[0];
 const notice = (h2.match(/<div[^>]*notice[^>]*>[\s\S]{0,300}?(purged|cleared)[\s\S]{0,100}?<\/div>/i) || [''])[0].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
 const rn = h.match(/'roc_flush_cache'\); data.append\('nonce', '([0-9a-f]+)'/); let roc = null;
 if (rn) { const fd = new FormData(); fd.append('action', 'roc_flush_cache'); fd.append('nonce', rn[1]); roc = (await (await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: fd })).text()).slice(0, 200); }
 return { before, after, notice, roc }; }
