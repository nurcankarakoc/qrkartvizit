<?php
require_once '../core/security.php';
ensure_session_started();
require_once '../core/db.php';
require_once '../core/security.php';
require_role_or_redirect($pdo, 'designer', '../auth/login.php');

$designer_id = $_SESSION['user_id'];

// New Orders pool
$stmt_new = $pdo->prepare("SELECT o.*, u.name as customer_name 
                           FROM orders o 
                           JOIN users u ON o.user_id = u.id
                           WHERE o.status IN ('pending', 'pending_design') 
                           ORDER BY o.created_at DESC");
$stmt_new->execute();
$new_orders = $stmt_new->fetchAll();

// Active Jobs: status = 'designing' or 'revision_requested' (some status might not exist yet, so we assume typical workflow)
$stmt_active = $pdo->prepare("SELECT o.*, u.name as customer_name 
                              FROM orders o 
                              JOIN users u ON o.user_id = u.id
                              WHERE o.status IN ('designing', 'revision_requested', 'awaiting_approval') 
                              ORDER BY o.created_at DESC");
$stmt_active->execute();
$active_orders = $stmt_active->fetchAll();

// Completed/Approved: status = 'approved'
$stmt_completed = $pdo->prepare("SELECT o.*, u.name as customer_name 
                                 FROM orders o 
                                 JOIN users u ON o.user_id = u.id
                                 WHERE o.status IN ('approved') 
                                 ORDER BY o.created_at DESC LIMIT 10");
$stmt_completed->execute();
$completed_orders = $stmt_completed->fetchAll();

// Printing pool
$stmt_printing = $pdo->prepare("SELECT o.*, u.name as customer_name 
                                FROM orders o 
                                JOIN users u ON o.user_id = u.id
                                WHERE o.status = 'printing' 
                                ORDER BY o.created_at DESC");
$stmt_printing->execute();
$printing_orders = $stmt_printing->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasarımcı Paneli — Zerosoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?php echo time(); ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="dashboard-body">

    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand-logotype">
                    <div class="mock-logo">Z</div>
                    <span>Zerosoft <small>Designer</small></span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="active"><a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Panel</a></li>
                    <li><a href="designs.php"><i data-lucide="image"></i> Tasarımlarım</a></li>
                    <li><a href="designs.php?filter=approved"><i data-lucide="check-circle"></i> Onaylananlar</a></li>
                    <li><a href="form-control.php"><i data-lucide="sliders-horizontal"></i> Form Kontrol Merkezi</a></li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?></div>
                    <div class="details">
                        <span class="name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                        <span class="role">Tasarımcı</span>
                    </div>
                </div>
                <a href="../processes/logout.php" class="logout-link"><i data-lucide="log-out"></i> Çıkış Yap</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <div class="header-search">
                    <div class="search-input-group">
                        <i data-lucide="search" class="search-icon"></i>
                        <input type="text" id="designer-search-input" placeholder="Sipariş veya müşteri ara..." autocomplete="off">
                        <button type="button" id="designer-search-go" class="search-go-btn">Ara</button>
                    </div>
                    <div id="designer-search-meta" class="search-meta" aria-live="polite"></div>
                    <div id="designer-search-suggestions" class="search-suggestions" hidden></div>
                </div>
                <div class="header-actions">
                    <div class="notification-btn">
                        <i data-lucide="bell"></i>
                        <span class="dot"></span>
                    </div>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="welcome-section">
                    <h1>Hoş Geldin, <?php echo explode(' ', $_SESSION['user_name'] ?? 'Dostum')[0]; ?>! 👋</h1>
                    <p>Bugün tasarım bekleyen <strong><?php echo count($new_orders); ?> yeni sipariş</strong> var.</p>
                </div>

                <div class="stats-grid-dashboard">
                    <div class="stat-card stat-card-link" data-scroll-target="new-orders-section" role="button" tabindex="0" aria-label="Yeni sipariş havuzu bölümüne git">
                        <div class="stat-icon new"><i data-lucide="plus-circle"></i></div>
                        <div class="stat-info">
                            <span class="label">Yeni Sipariş</span>
                            <span class="value"><?php echo count($new_orders); ?></span>
                        </div>
                    </div>
                    <div class="stat-card stat-card-link" data-scroll-target="active-orders-section" role="button" tabindex="0" aria-label="Aktif işler bölümüne git">
                        <div class="stat-icon active"><i data-lucide="clock"></i></div>
                        <div class="stat-info">
                            <span class="label">Aktif İşler</span>
                            <span class="value"><?php echo count($active_orders); ?></span>
                        </div>
                    </div>
                    <div class="stat-card stat-card-link" data-scroll-target="printing-orders-section" role="button" tabindex="0" aria-label="Baskıda olan işler bölümüne git">
                        <div class="stat-icon" style="background:#fff7ed; color:#ea580c;"><i data-lucide="printer"></i></div>
                        <div class="stat-info">
                            <span class="label">Baskıda</span>
                            <span class="value"><?php echo count($printing_orders); ?></span>
                        </div>
                    </div>
                    <div class="stat-card stat-card-link" data-go-url="designs.php?filter=approved" role="button" tabindex="0" aria-label="Onaylanan tasarımlara git">
                        <div class="stat-icon completed"><i data-lucide="check-circle-2"></i></div>
                        <div class="stat-info">
                            <span class="label">Onaylanan</span>
                            <span class="value"><?php echo count($completed_orders); ?></span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-tables">
                    <!-- New Orders Table -->
                    <section class="table-container" id="new-orders-section">
                        <div class="table-header">
                            <h2>Yeni Sipariş Havuzu</h2>
                            <a href="designs.php?filter=pending" class="view-all">Tümünü Gör</a>
                        </div>
                        <table class="data-table responsive-table">
                            <thead>
                                <tr>
                                    <th>Müşteri</th>
                                    <th>Paket</th>
                                    <th>Tarih</th>
                                    <th>Durum</th>
                                    <th>Aksiyon</th>
                                </tr>
                            </thead>
                            <tbody id="new-orders-body">
                                <?php if (empty($new_orders)): ?>
                                    <tr><td colspan="5" class="empty-state">Henüz yeni sipariş yok.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($new_orders as $order): ?>
                                        <tr
                                            data-search-row="new"
                                            data-order-id="<?php echo (int)$order['id']; ?>"
                                            data-customer="<?php echo htmlspecialchars((string)$order['customer_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-package="<?php echo htmlspecialchars((string)($order['package'] ?? 'classic'), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="pending"
                                        >
                                            <td data-label="Müşteri">
                                                <div class="customer-cell">
                                                    <div class="initials"><?php echo strtoupper(substr($order['customer_name'], 0, 1)); ?></div>
                                                    <span><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                                </div>
                                            </td>
                                            <td data-label="Paket"><span class="badge package-<?php echo strtolower($order['package'] ?? 'classic'); ?>"><?php echo htmlspecialchars($order['package'] ?? 'Classic'); ?></span></td>
                                            <td data-label="Tarih"><?php echo date('d.m.Y', strtotime($order['created_at'])); ?></td>
                                            <td data-label="Durum"><span class="status-dot pending"></span> Yeni Sipariş</td>
                                            <td data-label="Aksiyon" class="cell-actions"><a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn-action">İncele</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </section>

                    <!-- Active Jobs Table -->
                    <section class="table-container" id="active-orders-section">
                        <div class="table-header">
                            <h2>Üzerimdeki İşler</h2>
                            <a href="designs.php" class="view-all">Tümünü Gör</a>
                        </div>
                        <table class="data-table responsive-table">
                            <thead>
                                <tr>
                                    <th>Müşteri</th>
                                    <th>Durum</th>
                                    <th>Tarih</th>
                                    <th>Aksiyon</th>
                                </tr>
                            </thead>
                            <tbody id="active-orders-body">
                                <?php if (empty($active_orders)): ?>
                                    <tr><td colspan="4" class="empty-state">Şu an aktif bir işiniz bulunmuyor.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($active_orders as $order): ?>
                                        <tr
                                            data-search-row="active"
                                            data-order-id="<?php echo (int)$order['id']; ?>"
                                            data-customer="<?php echo htmlspecialchars((string)$order['customer_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-package="<?php echo htmlspecialchars((string)($order['package'] ?? 'classic'), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="<?php echo htmlspecialchars((string)$order['status'], ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <td data-label="Müşteri"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td data-label="Durum">
                                                <?php if($order['status'] == 'revision_requested'): ?>
                                                    <span class="status-chip revision">Revize İstendi</span>
                                                <?php elseif($order['status'] == 'awaiting_approval'): ?>
                                                    <span class="status-chip pending">Onay Bekliyor</span>
                                                <?php else: ?>
                                                    <span class="status-chip designing">Tasarım Yapılıyor</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Tarih"><?php echo date('d.m.Y', strtotime($order['created_at'])); ?></td>
                                            <td data-label="Aksiyon" class="cell-actions"><a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn-action primary">Detay</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </section>

                    <!-- Printing Orders Table -->
                    <section class="table-container" id="printing-orders-section">
                        <div class="table-header">
                            <h2>Baskıdaki İşler</h2>
                            <a href="designs.php?filter=approved" class="view-all">Tümünü Gör</a>
                        </div>
                        <table class="data-table responsive-table">
                            <thead>
                                <tr>
                                    <th>Müşteri</th>
                                    <th>Paket</th>
                                    <th>Baskı Tarihi</th>
                                    <th>Aksiyon</th>
                                </tr>
                            </thead>
                            <tbody id="printing-orders-body">
                                <?php if (empty($printing_orders)): ?>
                                    <tr><td colspan="4" class="empty-state">Baskıda olan sipariş bulunmuyor.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($printing_orders as $order): ?>
                                        <tr
                                            data-search-row="printing"
                                            data-order-id="<?php echo (int)$order['id']; ?>"
                                            data-customer="<?php echo htmlspecialchars((string)$order['customer_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="printing"
                                        >
                                            <td data-label="Müşteri">
                                                <div class="customer-cell">
                                                    <div class="initials" style="background:#fff7ed; color:#ea580c; border:1px solid #fed7aa;">
                                                        <?php echo strtoupper(substr($order['customer_name'], 0, 1)); ?>
                                                    </div>
                                                    <span><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                                </div>
                                            </td>
                                            <td data-label="Paket"><span class="badge" style="background:rgba(234,88,12,0.1); color:#ea580c; border:1px solid rgba(234,88,12,0.2);"><?php echo htmlspecialchars($order['package'] ?? 'Classic'); ?></span></td>
                                            <td data-label="Tarih"><?php echo date('d.m.Y', strtotime($order['created_at'])); ?></td>
                                            <td data-label="Aksiyon" class="cell-actions"><a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn-action">İncele</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </section>
                </div>
            </div>
        </main>
    </div>


    <style>
        .search-suggestions {
            margin-top: 0.45rem;
            max-width: 640px;
            border: 1px solid #dbe3ee;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 10px 25px rgba(2, 28, 43, 0.08);
            overflow: hidden;
            z-index: 25;
            position: relative;
        }

        .search-suggestions-list {
            margin: 0;
            padding: 0;
            list-style: none;
            max-height: 280px;
            overflow-y: auto;
        }

        .search-suggestion-btn {
            width: 100%;
            border: 0;
            border-bottom: 1px solid #eef2f7;
            background: transparent;
            text-align: left;
            padding: 0.7rem 0.9rem;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 0.18rem;
        }

        .search-suggestion-btn:last-child {
            border-bottom: 0;
        }

        .search-suggestion-btn:hover,
        .search-suggestion-btn:focus {
            background: #f8fafc;
            outline: none;
        }

        .search-suggestion-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #0f172a;
        }

        .search-suggestion-meta {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
        }

        .search-suggestions-note {
            padding: 0.55rem 0.9rem;
            font-size: 0.74rem;
            font-weight: 600;
            color: #64748b;
            background: #f8fafc;
            border-top: 1px solid #eef2f7;
        }
    </style>

    <script src="../assets/js/dashboard-mobile.js"></script>
    
    <script>
        lucide.createIcons();

        (function() {
            const searchInput = document.getElementById('designer-search-input');
            const searchGoBtn = document.getElementById('designer-search-go');
            const searchMeta = document.getElementById('designer-search-meta');
            const searchSuggestions = document.getElementById('designer-search-suggestions');
            const allRows = Array.from(document.querySelectorAll('tr[data-search-row]'));

            if (!searchInput) {
                return;
            }

            function normalizeSearchText(value) {
                return String(value || '')
                    .toLocaleLowerCase('tr-TR')
                    .replace(/\u0131/g, 'i')
                    .replace(/ı/g, 'i')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9#\s_-]+/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();
            }

            const rowModels = allRows.map((row) => {
                const orderId = String(row.dataset.orderId || '').trim();
                const customer = row.dataset.customer || '';
                const pkg = row.dataset.package || '';
                const status = row.dataset.status || '';
                const searchBlob = normalizeSearchText([orderId, customer, pkg, status, row.textContent].join(' '));
                const actionLink = row.querySelector('a[href*="order_details.php"]');
                return {
                    row,
                    orderId,
                    customer,
                    packageName: pkg,
                    status,
                    searchBlob,
                    actionHref: actionLink ? actionLink.getAttribute('href') : ''
                };
            });

            const totalRows = rowModels.length;

            function ensureSearchEmptyRow(tbody, rowId, message) {
                if (!tbody) return null;
                let row = document.getElementById(rowId);
                if (!row) {
                    row = document.createElement('tr');
                    row.id = rowId;
                    row.className = 'search-empty-row';
                    row.style.display = 'none';
                    row.innerHTML = '<td colspan="5"></td>';
                    tbody.appendChild(row);
                }
                const cell = row.querySelector('td');
                if (cell) {
                    cell.textContent = message;
                }
                return row;
            }

            const newOrdersBody = document.getElementById('new-orders-body');
            const activeOrdersBody = document.getElementById('active-orders-body');
            const printingOrdersBody = document.getElementById('printing-orders-body');
            const newEmptyRow = ensureSearchEmptyRow(newOrdersBody, 'search-empty-new-orders', 'Aramaya uygun yeni sipariş bulunamadı.');
            const activeEmptyRow = ensureSearchEmptyRow(activeOrdersBody, 'search-empty-active-orders', 'Aramaya uygun aktif iş bulunamadı.');
            const printingEmptyRow = ensureSearchEmptyRow(printingOrdersBody, 'search-empty-printing-orders', 'Aramaya uygun baskıdaki iş bulunamadı.');

            const suggestionLimit = 8;

            function getStatusLabel(status) {
                switch (status) {
                    case 'pending':
                    case 'pending_design':
                        return 'Yeni sipariş';
                    case 'designing':
                        return 'Tasarım aşamasında';
                    case 'revision_requested':
                        return 'Revize istendi';
                    case 'awaiting_approval':
                        return 'Onay bekliyor';
                    case 'approved':
                    case 'completed':
                        return 'Onaylandi';
                    default:
                        return status || '-';
                }
            }

            function renderSearchSuggestions(query, models) {
                if (!searchSuggestions) return;
                searchSuggestions.innerHTML = '';

                if (!query || query === '' || !Array.isArray(models) || models.length === 0) {
                    searchSuggestions.hidden = true;
                    return;
                }

                const available = models.filter((model) => model.actionHref);
                if (available.length === 0) {
                    searchSuggestions.hidden = true;
                    return;
                }

                const list = document.createElement('ul');
                list.className = 'search-suggestions-list';

                available.slice(0, suggestionLimit).forEach((model) => {
                    const li = document.createElement('li');
                    const btn = document.createElement('button');
                    const title = document.createElement('span');
                    const meta = document.createElement('span');

                    btn.type = 'button';
                    btn.className = 'search-suggestion-btn';
                    btn.dataset.href = model.actionHref;

                    title.className = 'search-suggestion-title';
                    title.textContent = `${model.customer || 'Müşteri'} (#${model.orderId || '-'})`;

                    meta.className = 'search-suggestion-meta';
                    meta.textContent = `${model.packageName || 'classic'} • ${getStatusLabel(model.status)}`;

                    btn.appendChild(title);
                    btn.appendChild(meta);
                    li.appendChild(btn);
                    list.appendChild(li);
                });

                searchSuggestions.appendChild(list);

                if (available.length > suggestionLimit) {
                    const note = document.createElement('div');
                    note.className = 'search-suggestions-note';
                    note.textContent = `${available.length} kayit bulundu. Ilk ${suggestionLimit} sonuc listeleniyor.`;
                    searchSuggestions.appendChild(note);
                }

                searchSuggestions.hidden = false;
            }

            function applySearch(rawValue) {
                const query = normalizeSearchText(rawValue);
                const pureOrderIdQuery = query.replace(/^#/, '');
                let visibleCount = 0;
                let singleMatchHref = '';
                let exactMatchHref = '';
                const matchedModels = [];

                rowModels.forEach((model) => {
                    const idMatch = pureOrderIdQuery !== '' && model.orderId === pureOrderIdQuery;
                    const isMatch = query === '' || idMatch || model.searchBlob.includes(query);
                    model.row.style.display = isMatch ? '' : 'none';
                    if (isMatch) {
                        visibleCount++;
                        matchedModels.push(model);
                        if (idMatch && exactMatchHref === '' && model.actionHref) {
                            exactMatchHref = model.actionHref;
                        }
                        if (singleMatchHref === '' && model.actionHref) {
                            singleMatchHref = model.actionHref;
                        }
                    }
                });

                const newVisible = newOrdersBody
                    ? Array.from(newOrdersBody.querySelectorAll('tr[data-search-row]')).filter((row) => row.style.display !== 'none').length
                    : 0;
                const printingVisible = printingOrdersBody
                    ? Array.from(printingOrdersBody.querySelectorAll('tr[data-search-row]')).filter((row) => row.style.display !== 'none').length
                    : 0;

                if (newEmptyRow) newEmptyRow.style.display = newVisible === 0 ? '' : 'none';
                if (activeEmptyRow) activeEmptyRow.style.display = activeVisible === 0 ? '' : 'none';
                if (printingEmptyRow) printingEmptyRow.style.display = printingVisible === 0 ? '' : 'none';

                if (searchMeta) {
                    if (query === '') {
                        searchMeta.textContent = totalRows > 0
                            ? `${totalRows} kayıt listeleniyor.`
                            : 'Arama yapılacak sipariş kaydı bulunmuyor.';
                    } else {
                        searchMeta.textContent = `${visibleCount} sonuç bulundu.`;
                    }
                }

                renderSearchSuggestions(query, matchedModels);
                return { query, visibleCount, singleMatchHref, exactMatchHref, matchedModels };
            }

            function goToSearchResult() {
                const result = applySearch(searchInput.value);
                if (result.query === '') {
                    if (searchMeta) {
                        searchMeta.textContent = 'Yönlendirme için sipariş no veya müşteri adı yazın.';
                    }
                    return;
                }
                if (result.exactMatchHref) {
                    window.location.href = result.exactMatchHref;
                    return;
                }
                if (result.visibleCount === 1 && result.singleMatchHref) {
                    window.location.href = result.singleMatchHref;
                    return;
                }
                if (searchMeta) {
                    searchMeta.textContent = result.visibleCount > 1
                        ? `${result.visibleCount} kayıt bulundu. Otomatik yönlendirme yapılmadı, listeden seçim yapın.`
                        : 'Eşleşen sipariş bulunamadı.';
                }
            }

            let debounceTimer = null;
            searchInput.addEventListener('input', (event) => {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(() => {
                    applySearch(event.target.value);
                }, 120);
            });

            searchInput.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    searchInput.value = '';
                    applySearch('');
                    return;
                }
                if (event.key === 'Enter') {
                    event.preventDefault();
                    goToSearchResult();
                }
            });

            if (searchGoBtn) {
                searchGoBtn.addEventListener('click', goToSearchResult);
            }

            if (searchSuggestions) {
                searchSuggestions.addEventListener('click', (event) => {
                    const itemBtn = event.target.closest('.search-suggestion-btn');
                    if (!itemBtn) return;
                    const href = itemBtn.dataset.href || '';
                    if (href) {
                        window.location.href = href;
                    }
                });

                document.addEventListener('click', (event) => {
                    if (!event.target.closest('.header-search')) {
                        searchSuggestions.hidden = true;
                    }
                });
            }

            const statCardLinks = Array.from(document.querySelectorAll('.stat-card-link'));
            statCardLinks.forEach((card) => {
                const goToTarget = () => {
                    const goUrl = card.dataset.goUrl;
                    const scrollTarget = card.dataset.scrollTarget;

                    if (goUrl) {
                        window.location.href = goUrl;
                        return;
                    }

                    if (scrollTarget) {
                        const targetEl = document.getElementById(scrollTarget);
                        if (targetEl) {
                            targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }
                };

                card.addEventListener('click', goToTarget);
                card.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        goToTarget();
                    }
                });
            });

            applySearch('');
        })();
    </script>
</body>
</html>
