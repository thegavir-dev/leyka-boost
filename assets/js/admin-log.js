document.addEventListener('DOMContentLoaded', function () {
	var rows = Array.prototype.slice.call(document.querySelectorAll('.leyka-boost-log-table tbody tr[data-message]'));
	var search = document.getElementById('leyka-boost-log-search');
	var module = document.getElementById('leyka-boost-log-module');
	var level = document.getElementById('leyka-boost-log-level');
	var clear = document.getElementById('leyka-boost-log-clear');
	var emptyFiltered = document.querySelector('.leyka-boost-log-empty--filtered');
	var pagination = document.querySelector('.leyka-boost-log-pagination');
	var paginationPages = pagination ? pagination.querySelector('.tablenav-pages') : null;
	var perPage = 50;
	var currentPage = 1;
	var filteredRows = rows.slice();

	function filterRows() {
		var text = search ? search.value.toLowerCase().trim() : '';
		var moduleValue = module ? module.value : '';
		var levelValue = level ? level.value : '';

		filteredRows = rows.filter(function (row) {
			var matchesText = !text || row.dataset.message.indexOf(text) !== -1;
			var matchesModule = !moduleValue || row.dataset.module === moduleValue;
			var matchesLevel = !levelValue || row.dataset.level === levelValue;

			return matchesText && matchesModule && matchesLevel;
		});

		currentPage = 1;
		renderPage();
	}

	function renderPage() {
		var totalPages = Math.max(1, Math.ceil(filteredRows.length / perPage));

		if (currentPage > totalPages) {
			currentPage = totalPages;
		}

		var start = (currentPage - 1) * perPage;
		var end = start + perPage;

		rows.forEach(function (row) {
			row.hidden = true;
		});

		filteredRows.slice(start, end).forEach(function (row, index) {
			row.hidden = false;
			row.cells[0].textContent = String(start + index + 1);
		});

		if (emptyFiltered) {
			emptyFiltered.hidden = rows.length === 0 || filteredRows.length > 0;
		}

		renderPagination(totalPages);
	}

	function renderPagination(totalPages) {
		if (!pagination || !paginationPages) {
			return;
		}

		if (filteredRows.length <= perPage) {
			pagination.hidden = true;
			paginationPages.innerHTML = '';
			return;
		}

		pagination.hidden = false;
		paginationPages.innerHTML = '';

		addPageButton('‹', currentPage - 1, currentPage === 1);

		for (var page = 1; page <= totalPages; page++) {
			if (page === 1 || page === totalPages || Math.abs(page - currentPage) <= 2) {
				addPageButton(String(page), page, false, page === currentPage);
			} else if (Math.abs(page - currentPage) === 3) {
				var dots = document.createElement('span');
				dots.className = 'pagination-links__dots';
				dots.textContent = '…';
				paginationPages.appendChild(dots);
			}
		}

		addPageButton('›', currentPage + 1, currentPage === totalPages);
	}

	function addPageButton(label, page, disabled, active) {
		var button = document.createElement('button');
		button.type = 'button';
		button.className = active ? 'button button-primary' : 'button';
		button.textContent = label;
		button.disabled = disabled || active;
		button.addEventListener('click', function () {
			currentPage = page;
			renderPage();
		});
		paginationPages.appendChild(button);
	}

	[search, module, level].forEach(function (control) {
		if (control) {
			control.addEventListener('input', filterRows);
			control.addEventListener('change', filterRows);
		}
	});

	if (clear) {
		clear.addEventListener('click', function () {
			if (!window.confirm(leykaBoostLog.confirmClear)) {
				return;
			}

			var body = new window.URLSearchParams();
			body.append('action', 'leyka_boost_clear_log');
			body.append('nonce', leykaBoostLog.logNonce);

			window.fetch(leykaBoostLog.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			}).then(function (response) {
				return response.json();
			}).then(function (data) {
				if (data.success) {
					window.location.reload();
				}
			});
		});
	}

	renderPage();
});
