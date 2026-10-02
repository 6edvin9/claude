// node build.js id,id,...   -> agst_el_build force=1 in batches of 6
const { admin } = require('./lib');
const ids = process.argv[2].split(',').map(Number);
admin(async (ids) => { const nonce = (await (await fetch('/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim(); const out = {};
  for (let k = 0; k < ids.length; k += 6) { const fd = new FormData(); fd.append('action', 'agst_el_build'); fd.append('nonce', nonce); fd.append('ids', ids.slice(k, k + 6).join(',')); fd.append('force', '1');
    const r = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: fd }); const t = await r.text(); try { Object.assign(out, JSON.parse(t).data); } catch (e) { out['batch' + k] = r.status + ' ' + t.slice(0, 200); } }
  return JSON.stringify(out); }, ids).then(console.log).catch(e => console.log('FAIL', e.message));
