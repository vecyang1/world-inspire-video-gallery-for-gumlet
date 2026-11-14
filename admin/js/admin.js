(function () {
	'use strict';

	if (typeof window.wivgAdminData !== 'object' || !window.wivgAdminData) {
		return;
	}

	var cfg = window.wivgAdminData;
	var REST_BASE = String(cfg.restBase || '');
	var NONCE = String(cfg.nonce || '');
	var DEFAULT_SLOT = Number(cfg.defaultSlot || 2);
	var I18N = cfg.i18n || {};

	var state = {
		audit: cfg.audit || {},
		tab: 'arrange',
		filter: 'all',
		search: ''
	};

	function esc(s) {
		return String(s || '').replace(/[&<>"']/g, function (c) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;'
			}[c];
		});
	}

	function escUrl(u) {
		var raw = String(u || '').trim();
		if (/^https?:\/\//i.test(raw) || raw.indexOf('/') === 0) {
			return esc(raw);
		}
		return '#';
	}

	function t(key, fallback) {
		return typeof I18N[key] === 'string' ? I18N[key] : fallback;
	}

	function apiCall(path, opts) {
		var options = Object.assign(
			{
				credentials: 'same-origin',
				headers: Object.assign(
					{
						'X-WP-Nonce': NONCE,
						'Content-Type': 'application/json'
					},
					(opts && opts.headers) || {}
				)
			},
			opts || {}
		);

		return fetch(REST_BASE + path, options).then(function (r) {
			return r
				.json()
				.catch(function () {
					return {};
				})
				.then(function (d) {
					if (!r.ok) {
						throw new Error(d.message || d.error || 'HTTP ' + r.status);
					}
					return d;
				});
		});
	}

	function syncUrlState(push) {
		var url = new URL(window.location.href);
		url.searchParams.set('tab', state.tab);
		if (state.filter && state.filter !== 'all') {
			url.searchParams.set('filter', state.filter);
		} else {
			url.searchParams.delete('filter');
		}
		if (push) {
			window.history.pushState({}, '', url.toString());
		} else {
			window.history.replaceState({}, '', url.toString());
		}
	}

	function readUrlState() {
		var url = new URL(window.location.href);
		var tab = url.searchParams.get('tab');
		if (['arrange', 'create', 'inventory', 'audit'].indexOf(tab) !== -1) {
			state.tab = tab;
		}
		var filter = url.searchParams.get('filter');
		if (['all', 'with_video', 'missing'].indexOf(filter) !== -1) {
			state.filter = filter;
		}
	}

	function renderProductArranger() {
		var wrap = document.getElementById('wivgProductList');
		if (!wrap) {
			return;
		}
		var q = (state.search || '').toLowerCase();
		var defaultSlot = (state.audit.site && Number(state.audit.site.default_slot)) || DEFAULT_SLOT;
		var prods = (state.audit.products || []).filter(function (p) {
			if (state.filter === 'with_video' && !p.has_video) {
				return false;
			}
			if (state.filter === 'missing' && p.has_video) {
				return false;
			}
			if (
				q &&
				String(p.id).indexOf(q) === -1 &&
				(p.title || '').toLowerCase().indexOf(q) === -1 &&
				(p.slug || '').toLowerCase().indexOf(q) === -1
			) {
				return false;
			}
			return true;
		});

		if (!prods.length) {
			wrap.innerHTML = '<div class="wivg-empty">' + esc(t('noProducts', 'No matching products found.')) + '</div>';
			return;
		}

		wrap.innerHTML = prods
			.map(function (p) {
				var statusBadge = p.has_video
					? '<span class="wivg-status wivg-status-ok">' + Number(p.video_count) + ' ' + esc(t('videosActive', 'Video(s) Active')) + '</span>'
					: '<span class="wivg-status wivg-status-warn">' + esc(t('missingVideo', 'Missing Video')) + '</span>';

				var slotsHtml = (p.slots || [])
					.map(function (s, idx) {
						var v = s.video;
						var vBadge = v
							? '<span class="wivg-status wivg-status-ok">' +
							  esc(String(v.source_type || '').toUpperCase()) +
							  (v.autoplay ? ' &middot; ' + esc(t('auto', 'Auto')) : '') +
							  '</span>'
							: '<span class="wivg-slot-label">' + esc(t('imageSlide', 'Image Slide')) + '</span>';
						var moveLeft =
							idx > 0
								? '<button type="button" class="wivg-btn wivg-btn-sm wivg-move-slot" data-pid="' +
								  Number(p.id) +
								  '" data-from="' +
								  idx +
								  '" data-to="' +
								  (idx - 1) +
								  '" title="' +
								  esc(t('moveLeft', 'Move Left')) +
								  '">&larr;</button>'
								: '';
						var moveRight =
							idx < p.slots.length - 1
								? '<button type="button" class="wivg-btn wivg-btn-sm wivg-move-slot" data-pid="' +
								  Number(p.id) +
								  '" data-from="' +
								  idx +
								  '" data-to="' +
								  (idx + 1) +
								  '" title="' +
								  esc(t('moveRight', 'Move Right')) +
								  '">&rarr;</button>'
								: '';
						var removeVid = v
							? '<button type="button" class="wivg-btn wivg-btn-sm wivg-btn-danger wivg-remove-vid" data-pid="' +
							  Number(p.id) +
							  '" data-slot="' +
							  Number(s.slot) +
							  '">' +
							  esc(t('detach', 'Detach')) +
							  '</button>'
							: '';

						return (
							'<div class="wivg-slot-card ' +
							(s.has_video ? 'has-video' : '') +
							'">' +
							'<div class="wivg-slot-head">' +
							'<span>' +
							esc(t('slotPrefix', 'Slot #')) +
							Number(s.slot) +
							(s.slot === 1 ? ' (' + esc(t('mainLabel', 'Main')) + ')' : '') +
							'</span>' +
							'<span class="wivg-slot-id">#' +
							Number(s.attachment_id) +
							'</span>' +
							'</div>' +
							(s.thumb_url ? '<img class="wivg-slot-thumb" src="' + escUrl(s.thumb_url) + '" alt="">' : '<div class="wivg-slot-thumb"></div>') +
							vBadge +
							(v
								? '<div class="wivg-slot-url"><a href="' +
								  escUrl(v.video_url) +
								  '" target="_blank" rel="noopener">' +
								  esc(v.video_id || v.video_url) +
								  '</a></div>'
								: '') +
							'<div class="wivg-slot-actions">' +
							moveLeft +
							moveRight +
							removeVid +
							'</div>' +
							'</div>'
						);
					})
					.join('');

				return (
					'<div class="wivg-prod-row" data-pid="' +
					Number(p.id) +
					'">' +
					'<div class="wivg-prod-head">' +
					'<div><strong class="wivg-prod-title">#' +
					Number(p.id) +
					' &mdash; ' +
					esc(p.title) +
					'</strong> ' +
					statusBadge +
					'<div class="wivg-prod-sub"><a href="' +
					escUrl(p.url) +
					'" target="_blank" rel="noopener">' +
					esc(t('viewPdp', 'View Storefront Product')) +
					' &rarr;</a></div></div>' +
					'<div class="wivg-inline-controls">' +
					'<input type="text" class="wivg-input wivg-input-inline-vid wivg-inline-vid" data-pid="' +
					Number(p.id) +
					'" placeholder="' +
					esc(t('inlinePlaceholder', 'YouTube URL/ID or Gumlet/MP4')) +
					'">' +
					'<input type="number" class="wivg-input wivg-input-inline-slot wivg-inline-slot" data-pid="' +
					Number(p.id) +
					'" value="' +
					Number(defaultSlot) +
					'" min="1" max="20" title="' +
					esc(t('targetSlot', 'Target Slot #')) +
					'">' +
					'<button type="button" class="wivg-btn wivg-btn-primary wivg-btn-sm wivg-inline-insert" data-pid="' +
					Number(p.id) +
					'">' +
					esc(t('insertAtSlot', 'Insert at Slot')) +
					'</button>' +
					'</div>' +
					'</div>' +
					'<div class="wivg-slots-strip">' +
					slotsHtml +
					'</div>' +
					'</div>'
				);
			})
			.join('');
	}

	function renderInventory() {
		var wrap = document.getElementById('wivgAssetTableWrap');
		if (!wrap) {
			return;
		}
		var gumlet = (state.audit.assets && state.audit.assets.gumlet) || [];
		var local = (state.audit.assets && state.audit.assets.local_mp4) || [];
		var rows = gumlet
			.map(function (a) {
				return Object.assign({ host: 'Gumlet CDN' }, a);
			})
			.concat(
				local.map(function (a) {
					return Object.assign({ host: 'Local MP4' }, a);
				})
			);

		if (!rows.length) {
			wrap.innerHTML = '<p class="wivg-subtitle">' + esc(t('noAssets', 'No video attachments registered in WP Media Library yet.')) + '</p>';
			return;
		}

		wrap.innerHTML =
			'<table class="widefat striped"><thead><tr>' +
			'<th>' +
			esc(t('colId', 'ID')) +
			'</th>' +
			'<th>' +
			esc(t('colTitle', 'Title')) +
			'</th>' +
			'<th>' +
			esc(t('colHost', 'Host / Size')) +
			'</th>' +
			'<th>' +
			esc(t('colDims', 'Dimensions')) +
			'</th>' +
			'<th>' +
			esc(t('colUrl', 'URL')) +
			'</th>' +
			'</tr></thead><tbody>' +
			rows
				.map(function (r) {
					return (
						'<tr>' +
						'<td>#' +
						Number(r.id) +
						'</td>' +
						'<td><strong>' +
						esc(r.title) +
						'</strong></td>' +
						'<td>' +
						esc(r.host) +
						(r.bytes ? ' (' + (Number(r.bytes) / 1048576).toFixed(1) + ' MB)' : '') +
						'</td>' +
						'<td>' +
						(r.width && r.height ? Number(r.width) + 'x' + Number(r.height) : '&mdash;') +
						(r.duration ? ' &middot; ' + esc(r.duration) : '') +
						'</td>' +
						'<td class="wivg-mono"><a href="' +
						escUrl(r.url) +
						'" target="_blank" rel="noopener">' +
						esc(r.url) +
						'</a></td>' +
						'</tr>'
					);
				})
				.join('') +
			'</tbody></table>';
	}

	function renderAuditIssues() {
		var wrap = document.getElementById('wivgAuditIssuesWrap');
		if (!wrap) {
			return;
		}
		var issues = state.audit.issues || [];
		if (!issues.length) {
			wrap.innerHTML = '<div class="wivg-status wivg-status-ok">' + esc(t('allHealthy', 'All published products have verified gallery videos and zero postmeta stub clutter.')) + '</div>';
			return;
		}
		wrap.innerHTML = issues
			.map(function (iss) {
				return (
					'<div class="wivg-issue-row">' +
					'<div><strong>' +
					esc(iss.code) +
					'</strong>: ' +
					esc(iss.message) +
					'</div>' +
					'<span class="wivg-status wivg-status-warn">' +
					Number(iss.count || 0) +
					'</span>' +
					'</div>'
				);
			})
			.join('');
	}

	function renderAll() {
		document.querySelectorAll('.wivg-nav-tab').forEach(function (el) {
			el.classList.toggle('is-active', el.getAttribute('data-tab') === state.tab);
		});
		document.querySelectorAll('.wivg-panel').forEach(function (el) {
			el.classList.toggle('is-active', el.id === 'wivg-panel-' + state.tab);
		});
		document.querySelectorAll('#wivgProdFilters [data-filter]').forEach(function (el) {
			el.classList.toggle('is-active', el.getAttribute('data-filter') === state.filter);
		});

		var sum = state.audit.summary || {};
		var elCov = document.getElementById('wivgKpiCoverage');
		var elWith = document.getElementById('wivgKpiWithVid');
		var elTot = document.getElementById('wivgKpiTotalProd');
		var elMiss = document.getElementById('wivgKpiMissing');
		var elBind = document.getElementById('wivgKpiBindings');
		var elStub = document.getElementById('wivgKpiStubs');

		if (elCov) elCov.textContent = (sum.coverage_pct || 0) + '%';
		if (elWith) elWith.textContent = String(sum.products_with_video || 0);
		if (elTot) elTot.textContent = String(sum.published_products || 0);
		if (elMiss) elMiss.textContent = String(sum.products_missing_video || 0);
		if (elBind) elBind.textContent = String(sum.total_video_bindings || 0);
		if (elStub) elStub.textContent = String(sum.empty_stub_count || 0);

		var sel = document.getElementById('wivgQuickProductSelect');
		if (sel) {
			sel.innerHTML = (state.audit.products || [])
				.map(function (p) {
					return (
						'<option value="' +
						Number(p.id) +
						'">#' +
						Number(p.id) +
						' &mdash; ' +
						esc(p.title) +
						(p.has_video ? ' (' + esc(t('hasVideo', 'Has Video')) + ')' : ' (' + esc(t('missingVideo', 'Missing Video')) + ')') +
						'</option>'
					);
				})
				.join('');
		}

		renderProductArranger();
		renderInventory();
		renderAuditIssues();
	}

	function refreshAudit() {
		return apiCall('/audit').then(function (data) {
			state.audit = data;
			renderAll();
			return data;
		});
	}

	document.querySelectorAll('.wivg-nav-tab').forEach(function (tab) {
		tab.addEventListener('click', function (e) {
			e.preventDefault();
			state.tab = this.getAttribute('data-tab');
			syncUrlState(true);
			renderAll();
		});
	});

	document.querySelectorAll('.wivg-kpi[data-jump-tab]').forEach(function (kpi) {
		kpi.addEventListener('click', function () {
			state.tab = this.getAttribute('data-jump-tab');
			if (this.getAttribute('data-jump-filter')) {
				state.filter = this.getAttribute('data-jump-filter');
			}
			syncUrlState(true);
			renderAll();
		});
	});

	document.querySelectorAll('#wivgProdFilters [data-filter]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			state.filter = this.getAttribute('data-filter');
			syncUrlState(true);
			renderAll();
		});
	});

	var searchEl = document.getElementById('wivgProdSearch');
	if (searchEl) {
		searchEl.addEventListener('input', function () {
			state.search = this.value.trim();
			renderProductArranger();
		});
	}

	var refreshBtn = document.getElementById('wivgRefreshAuditBtn');
	if (refreshBtn) {
		refreshBtn.addEventListener('click', refreshAudit);
	}

	var productList = document.getElementById('wivgProductList');
	if (productList) {
		productList.addEventListener('click', function (e) {
			var insBtn = e.target.closest('.wivg-inline-insert');
			if (insBtn) {
				var pid = insBtn.getAttribute('data-pid');
				var row = insBtn.closest('.wivg-prod-row');
				var vid = row.querySelector('.wivg-inline-vid').value.trim();
				var slot = parseInt(row.querySelector('.wivg-inline-slot').value || String(DEFAULT_SLOT), 10);
				if (!vid) {
					window.alert(t('enterVideoFirst', 'Enter a YouTube URL/ID, Gumlet link, or MP4 ID first.'));
					return;
				}
				insBtn.disabled = true;
				apiCall('/insert', {
					method: 'POST',
					body: JSON.stringify({ product_id: Number(pid), video: vid, slot: slot })
				})
					.then(refreshAudit)
					.catch(function (err) {
						window.alert(err.message);
					})
					.finally(function () {
						insBtn.disabled = false;
					});
				return;
			}

			var rmBtn = e.target.closest('.wivg-remove-vid');
			if (rmBtn) {
				var rmPid = Number(rmBtn.getAttribute('data-pid'));
				var rmSlot = Number(rmBtn.getAttribute('data-slot'));
				rmBtn.disabled = true;
				apiCall('/arrange', {
					method: 'POST',
					body: JSON.stringify({ product_id: rmPid, remove_slot: rmSlot })
				})
					.then(refreshAudit)
					.catch(function (err) {
						window.alert(err.message);
					})
					.finally(function () {
						rmBtn.disabled = false;
					});
				return;
			}

			var mvBtn = e.target.closest('.wivg-move-slot');
			if (mvBtn) {
				var mvPid = Number(mvBtn.getAttribute('data-pid'));
				var fromIdx = Number(mvBtn.getAttribute('data-from'));
				var toIdx = Number(mvBtn.getAttribute('data-to'));
				var prod = (state.audit.products || []).find(function (x) {
					return x.id === mvPid;
				});
				if (!prod) {
					return;
				}
				var ids = prod.slots.map(function (s) {
					return s.attachment_id;
				});
				var tmp = ids[fromIdx];
				ids[fromIdx] = ids[toIdx];
				ids[toIdx] = tmp;
				mvBtn.disabled = true;
				apiCall('/arrange', {
					method: 'POST',
					body: JSON.stringify({ product_id: mvPid, slot_order: ids })
				})
					.then(refreshAudit)
					.catch(function (err) {
						window.alert(err.message);
					})
					.finally(function () {
						mvBtn.disabled = false;
					});
			}
		});
	}

	var probeBtn = document.getElementById('wivgProbeBtn');
	if (probeBtn) {
		probeBtn.addEventListener('click', function () {
			var self = this;
			var out = document.getElementById('wivgProbeOutput');
			var val = document.getElementById('wivgProbeInput').value.trim();
			self.disabled = true;
			apiCall('/probe', { method: 'POST', body: JSON.stringify({ input: val }) })
				.then(function (res) {
					out.innerHTML =
						'<div class="wivg-probe-box">' +
						'<div><strong>Source:</strong> ' +
						esc(res.source_type) +
						' &middot; <strong>Reachable:</strong> ' +
						(res.playback && res.playback.reachable ? 'YES (HTTP ' + Number(res.playback.http_status) + ')' : 'NO') +
						'</div>' +
						'<div class="wivg-mono">' +
						esc(res.url) +
						'</div>' +
						(res.metadata && res.metadata.title ? '<div><strong>Title:</strong> ' + esc(res.metadata.title) + '</div>' : '') +
						'</div>';
				})
				.catch(function (err) {
					out.innerHTML = '<div class="wivg-err-text">' + esc(err.message) + '</div>';
				})
				.finally(function () {
					self.disabled = false;
				});
		});
	}

	var createBtn = document.getElementById('wivgCreateAssetBtn');
	if (createBtn) {
		createBtn.addEventListener('click', function () {
			var self = this;
			var out = document.getElementById('wivgProbeOutput');
			var val = document.getElementById('wivgProbeInput').value.trim();
			self.disabled = true;
			apiCall('/create', { method: 'POST', body: JSON.stringify({ input: val }) })
				.then(function (res) {
					return refreshAudit().then(function () {
						out.innerHTML =
							'<div class="wivg-status wivg-status-ok">Registered ' +
							esc(res.source_type || 'video') +
							' asset #' +
							esc(res.id || res.poster_attachment_id || '') +
							' (' +
							esc(res.url || '') +
							').</div>';
					});
				})
				.catch(function (err) {
					out.innerHTML = '<div class="wivg-err-text">' + esc(err.message) + '</div>';
				})
				.finally(function () {
					self.disabled = false;
				});
		});
	}

	var quickInsertBtn = document.getElementById('wivgQuickInsertBtn');
	if (quickInsertBtn) {
		quickInsertBtn.addEventListener('click', function () {
			var self = this;
			var out = document.getElementById('wivgProbeOutput');
			var val = document.getElementById('wivgProbeInput').value.trim();
			var pid = Number(document.getElementById('wivgQuickProductSelect').value || 0);
			var slot = Number(document.getElementById('wivgQuickSlotInput').value || DEFAULT_SLOT);
			self.disabled = true;
			apiCall('/insert', {
				method: 'POST',
				body: JSON.stringify({ product_id: pid, video: val, slot: slot })
			})
				.then(refreshAudit)
				.then(function () {
					out.innerHTML = '<div class="wivg-status wivg-status-ok">Inserted into Product #' + pid + ' at Slot #' + slot + '.</div>';
				})
				.catch(function (err) {
					out.innerHTML = '<div class="wivg-err-text">' + esc(err.message) + '</div>';
				})
				.finally(function () {
					self.disabled = false;
				});
		});
	}

	var directUploadBtn = document.getElementById('wivgDirectUploadBtn');
	if (directUploadBtn) {
		directUploadBtn.addEventListener('click', function () {
			var self = this;
			var fileEl = document.getElementById('wivgDirectFile');
			var st = document.getElementById('wivgDirectUploadStatus');
			var file = fileEl && fileEl.files && fileEl.files[0];
			if (!file) {
				st.textContent = t('selectMp4', 'Select an MP4 file first.');
				return;
			}
			self.disabled = true;
			st.textContent = t('requestingSession', 'Requesting pre-signed Gumlet upload session...');
			var sessRef = null;
			apiCall('/gumlet/upload-session', {
				method: 'POST',
				body: JSON.stringify({ title: file.name.replace(/\.[^.]+$/, '') })
			})
				.then(function (sess) {
					sessRef = sess;
					st.textContent = 'Uploading ' + (file.size / 1048576).toFixed(1) + ' MB directly to Gumlet S3...';
					return fetch(sess.upload_url, {
						method: 'PUT',
						headers: { 'Content-Type': file.type || 'video/mp4' },
						body: file
					});
				})
				.then(function (putResp) {
					if (!putResp.ok) {
						throw new Error('S3 PUT failed: HTTP ' + putResp.status);
					}
					document.getElementById('wivgProbeInput').value = sessRef.url;
					st.textContent = 'Uploaded Asset ' + sessRef.asset_id + '. Registering in WP Media...';
					return apiCall('/create', {
						method: 'POST',
						body: JSON.stringify({ input: sessRef.url, title: file.name.replace(/\.[^.]+$/, '') })
					});
				})
				.then(refreshAudit)
				.then(function () {
					st.textContent = 'Ready: ' + sessRef.url;
				})
				.catch(function (err) {
					st.textContent = err.message;
				})
				.finally(function () {
					self.disabled = false;
				});
		});
	}

	var pruneBtn = document.getElementById('wivgPruneStubsBtn');
	if (pruneBtn) {
		pruneBtn.addEventListener('click', function () {
			var self = this;
			self.disabled = true;
			apiCall('/arrange', {
				method: 'POST',
				body: JSON.stringify({ prune_all_stubs: true })
			})
				.then(refreshAudit)
				.catch(function (err) {
					window.alert(err.message);
				})
				.finally(function () {
					self.disabled = false;
				});
		});
	}

	var saveSetBtn = document.getElementById('wivgSaveSettingsBtn');
	if (saveSetBtn) {
		saveSetBtn.addEventListener('click', function () {
			var self = this;
			var st = document.getElementById('wivgSetStatus');
			self.disabled = true;
			var payload = {
				brand_name: document.getElementById('wivgSetBrand').value.trim(),
				youtube_channel_url: document.getElementById('wivgSetYtChannel').value.trim(),
				gumlet_workspace_id: document.getElementById('wivgSetGumletWs').value.trim(),
				default_slot: Number(document.getElementById('wivgSetDefaultSlot').value || 2),
				privacy_mode: document.getElementById('wivgSetPrivacyMode') && document.getElementById('wivgSetPrivacyMode').checked ? 1 : 0
			};
			var apiKey = document.getElementById('wivgSetGumletKey').value.trim();
			if (apiKey) {
				payload.gumlet_api_key = apiKey;
			}
			apiCall('/settings', {
				method: 'POST',
				body: JSON.stringify(payload)
			})
				.then(function () {
					document.getElementById('wivgSetGumletKey').value = '';
					return refreshAudit();
				})
				.then(function () {
					st.textContent = t('saved', 'Saved.');
				})
				.catch(function (err) {
					st.textContent = err.message;
				})
				.finally(function () {
					self.disabled = false;
				});
		});
	}

	window.addEventListener('popstate', function () {
		readUrlState();
		renderAll();
	});
	readUrlState();
	renderAll();
})();
