/* Catalog Release admin page: runs analyze / apply / rollback step by step in small batches. */
(function () {
	'use strict';
	var $ = function (id) { return document.getElementById(id); };
	var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };

	function call(data) {
		var fd = new FormData();
		fd.append('action', 'agxr'); fd.append('nonce', AGXR.nonce);
		Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
		return fetch(AGXR.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
			return r.text().then(function (t) {
				var j; try { j = JSON.parse(t); } catch (e) { throw new Error('Server error (HTTP ' + r.status + '): ' + t.replace(/<[^>]+>/g, ' ').slice(0, 300)); }
				if (!j.success) { throw new Error((j.data && j.data.message) || 'Request failed'); }
				return j.data;
			});
		});
	}

	function stepBox(root, step) {
		var d = document.createElement('div');
		d.className = 'agxr-step';
		d.innerHTML = '<h3>' + esc(AGXR.labels[step] || step) + ' <small class="agxr-count"></small></h3><div class="agxr-body"></div>';
		root.appendChild(d);
		return d;
	}

	function render(box, results) {
		var body = box.querySelector('.agxr-body');
		results.forEach(function (r) {
			var html = '<div style="margin:6px 0"><b>' + esc(r.label) + '</b>';
			(r.issues || []).forEach(function (i) { html += '<div class="lvl-' + esc(i.level) + '">' + esc(i.level.toUpperCase()) + ': ' + esc(i.text) + '</div>'; });
			if ((r.changes || []).length) {
				html += '<table class="widefat striped" style="margin-top:4px"><tbody>';
				r.changes.forEach(function (c) { html += '<tr><td style="width:22%">' + esc(c.field) + '</td><td style="width:36%">' + esc(c.before) + '</td><td>→ ' + esc(c.after) + (c.note ? ' <i>(' + esc(c.note) + ')</i>' : '') + '</td></tr>'; });
				html += '</tbody></table>';
			}
			body.insertAdjacentHTML('beforeend', html + '</div>');
		});
	}

	function runStep(mode, step, box, extra, tally) {
		var size = step === 'products' || step === 'variations' ? 10 : 25;
		function next(offset) {
			return call(Object.assign({ op: mode, step: step, offset: offset, size: size }, extra)).then(function (d) {
				render(box, d.results);
				d.results.forEach(function (r) {
					tally.changes += (r.changes || []).length;
					(r.issues || []).forEach(function (i) { tally[i.level] = (tally[i.level] || 0) + 1; });
					if ((r.changes || []).length) { tally.items++; }
				});
				box.querySelector('.agxr-count').textContent = '(' + (d.next == null ? d.total : d.next) + ' of ' + d.total + ' checked)';
				return d.next == null ? null : next(d.next);
			});
		}
		return next(0);
	}

	function runAll(mode, root, extra) {
		root.innerHTML = '';
		var tally = { changes: 0, items: 0, block: 0, error: 0, warn: 0 };
		var chain = Promise.resolve();
		AGXR.steps.forEach(function (step) {
			chain = chain.then(function () {
				var box = stepBox(root, step);
				var before = tally.items;
				return runStep(mode, step, box, extra, tally).then(function () {
					if (tally.items === before && !box.querySelector('.lvl-warn,.lvl-error,.lvl-block')) { box.querySelector('.agxr-body').innerHTML = '<i>No changes.</i>'; }
					if (mode === 'apply' && tally.block) { throw new Error('Stopped by a safety check in "' + (AGXR.labels[step] || step) + '". Already-applied steps are saved in the journal (deactivate or Roll back to undo).'); }
				});
			});
		});
		return chain.then(function () { return tally; });
	}

	var an = $('agxr-analyze'), ap = $('agxr-apply'), rb = $('agxr-rollback'), ex = $('agxr-export'), st = $('agxr-selftest');
	if (an) an.addEventListener('click', function () {
		an.disabled = true; ap.disabled = true;
		runAll('analyze', $('agxr-report'), {}).then(function (t) {
			var msg = 'Analysis done: ' + t.items + ' items with ' + t.changes + ' field changes. Blocking: ' + t.block + ', errors: ' + t.error + ', warnings: ' + t.warn + '.';
			$('agxr-report').insertAdjacentHTML('afterbegin', '<p><b>' + esc(msg) + '</b></p>');
			return call({ op: 'analyzed', summary: JSON.stringify(t) }).then(function () { ap.disabled = t.block > 0; });
		}).catch(function (e) { $('agxr-report').insertAdjacentHTML('afterbegin', '<p class="lvl-error">' + esc(e.message) + '</p>'); }).then(function () { an.disabled = false; });
	});
	if (ap) ap.addEventListener('click', function () {
		if (!$('agxr-backup').checked) { alert('Tick the backup confirmation first.'); return; }
		if (!confirm('Apply the catalog release to ' + location.host + '? Every change is journaled and can be rolled back.')) { return; }
		ap.disabled = true; an.disabled = true;
		var run = 'run' + Date.now();
		runAll('apply', $('agxr-apply-log'), { backup: 1, include_edited: $('agxr-edited').checked ? 1 : 0, run: run }).then(function (t) {
			$('agxr-apply-log').insertAdjacentHTML('afterbegin', '<p><b>Release applied: ' + t.items + ' items, ' + t.changes + ' field changes. Items skipped with an error (left unchanged): ' + t.error + '. Warnings: ' + t.warn + '.</b></p>');
		}).catch(function (e) { $('agxr-apply-log').insertAdjacentHTML('afterbegin', '<p class="lvl-error">' + esc(e.message) + '</p>'); }).then(function () { an.disabled = false; });
	});
	if (rb) rb.addEventListener('click', function () {
		var c = $('agxr-confirm').value;
		if (c !== 'ROLLBACK') { alert('Type ROLLBACK to confirm.'); return; }
		rb.disabled = true;
		var log = $('agxr-rollback-log'); log.innerHTML = '';
		var notes = [];
		(function loop() {
			call({ op: 'rollback', confirm: c }).then(function (d) {
				notes = notes.concat(d.notes || []);
				log.innerHTML = '<p>Restored ' + d.done + ' values in this batch; ' + d.left + ' left.</p>' + (notes.length ? '<pre>' + esc(notes.join('\n')) + '</pre>' : '');
				if (d.left > 0 && d.done > 0) { loop(); } else { log.insertAdjacentHTML('afterbegin', '<p><b>' + (d.left ? 'Stopped with ' + d.left + ' values left (see notes).' : 'Rollback finished.') + '</b></p>'); rb.disabled = false; }
			}).catch(function (e) { log.insertAdjacentHTML('afterbegin', '<p class="lvl-error">' + esc(e.message) + '</p>'); rb.disabled = false; });
		})();
	});
	if (ex) ex.addEventListener('click', function () {
		ex.disabled = true; $('agxr-export-log').textContent = 'Building…';
		call({ op: 'export' }).then(function (d) { $('agxr-export-log').innerHTML = '<pre>' + esc(JSON.stringify(d, null, 1)) + '</pre><p>Reload the page for the download link.</p>'; })
			.catch(function (e) { $('agxr-export-log').innerHTML = '<p class="lvl-error">' + esc(e.message) + '</p>'; }).then(function () { ex.disabled = false; });
	});
	if (st) st.addEventListener('change', function () { call({ op: 'selftest', on: st.checked ? 1 : 0 }).catch(function (e) { alert(e.message); }); });
})();
