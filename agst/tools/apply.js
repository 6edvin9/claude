// node apply.js payload.json [batch]  -> agst_media_apply per product
const { admin } = require('./lib'); const fs = require('fs');
const data = JSON.parse(fs.readFileSync(process.argv[2], 'utf8')); const keys = Object.keys(data);
admin(async ({ data, keys }) => { const nonce = (await (await fetch('/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim(); const out = {};
  for (const k of keys) { const fd = new FormData(); fd.append('action', 'agst_media_apply'); fd.append('nonce', nonce); fd.append('payload', JSON.stringify({ [k]: data[k] }));
    const r = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: fd }); const t = await r.text(); try { Object.assign(out, JSON.parse(t).data); } catch (e) { out[k] = r.status + ' ' + t.slice(0, 300); } }
  return JSON.stringify(out); }, { data, keys }).then(console.log).catch(e => console.log('FAIL', e.message));
