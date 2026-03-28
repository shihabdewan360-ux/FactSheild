<?php require_once 'includes/config.php'; ?>
<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/navbar.php'; ?>

<?php
// 1. Capture Filters & Pagination State
$search = $_GET['search'] ?? '';
$verdict = $_GET['verdict'] ?? 'all';
$category = $_GET['category'] ?? 'text'; 
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 9; // 9 items per page (Perfect 3x3 grid on desktop)
$offset = ($page - 1) * $limit;

// 2. Build the Dynamic SQL Query
$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(content LIKE ? OR ai_summary LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($verdict !== 'all') {
    $where[] = "verdict = ?";
    $params[] = $verdict;
}
if ($category !== 'all') {
    $where[] = "content_type = ?";
    $params[] = $category;
}

$whereClause = implode(" AND ", $where);
$orderClause = ($sort === 'oldest') ? "MAX(analyzed_at) ASC" : "MAX(analyzed_at) DESC";

// 3. Deduplication & Pagination Math
// We use GROUP BY content to ensure if 10 people search "Trump is dead", it only shows up once!
$countSql = "SELECT COUNT(DISTINCT content) FROM articles WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalArticles = $countStmt->fetchColumn();
$totalPages = ceil($totalArticles / $limit);

// Fetch the actual deduplicated rows (Strictly the newest entry per unique content)
$sql = "SELECT a.* FROM articles a
        INNER JOIN (
            SELECT MAX(id) as max_id
            FROM articles
            WHERE $whereClause
            GROUP BY content
        ) latest ON a.id = latest.max_id
        ORDER BY " . (($sort === 'oldest') ? "a.analyzed_at ASC" : "a.analyzed_at DESC") . "
        LIMIT $limit OFFSET $offset";
        
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$journalEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique categories dynamically from the database for the dropdown
$catStmt = $pdo->query("SELECT DISTINCT content_type FROM articles WHERE content_type IS NOT NULL");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<main class="journal-page">
    <div class="container">
        
        <div class="breadcrumbs">
            <a href="index.php">Home</a> > <span>Journal</span>
        </div>

        <div class="journal-header">
            <h1 class="landing-title" style="font-size: 3.5rem; margin-bottom: 8px;">Fact Check Journal</h1>
            <p class="landing-subtitle" style="margin-bottom: 32px;">
                <span class="live-dot-small" style="background-color: var(--accent-green); box-shadow: 0 0 10px var(--accent-green); animation: pulseGreen 2s infinite;"></span>
                Browse all verified content. Updated in real time
            </p>
        </div>

        <form method="GET" action="journal.php" id="journal-filter-form">
            <div class="journal-filter-bar">
                <div class="search-input-wrapper">
                    <span style="opacity: 0.5;">🔍</span>
                    <input type="text" name="search" placeholder="Search verified articles" class="journal-search" value="<?= htmlspecialchars($search) ?>" onkeypress="if(event.key === 'Enter') this.form.submit();">
                </div>

                <div class="journal-filters">
                    <div class="filter-group">
                        <span class="filter-label">Verdict</span>
                        <input type="hidden" name="verdict" id="verdict-input" value="<?= htmlspecialchars($verdict) ?>">
                        <div class="filter-pills">
                            <button type="button" class="filter-pill <?= $verdict=='all'?'active':'' ?>" onclick="document.getElementById('verdict-input').value='all'; this.form.submit();">All</button>
                            <button type="button" class="filter-pill pill-real <?= $verdict=='real'?'active':'' ?>" onclick="document.getElementById('verdict-input').value='real'; this.form.submit();"><span class="pill-dot bg-green"></span> Real</button>
                            <button type="button" class="filter-pill pill-fake <?= $verdict=='fake'?'active':'' ?>" onclick="document.getElementById('verdict-input').value='fake'; this.form.submit();"><span class="pill-dot bg-red"></span> Fake</button>
                            <button type="button" class="filter-pill pill-suspicious <?= $verdict=='suspicious'?'active':'' ?>" onclick="document.getElementById('verdict-input').value='suspicious'; this.form.submit();"><span class="pill-dot bg-yellow"></span> Suspicious</button>
                        </div>
                    </div>
                    
                    <div class="filter-group dropdown-group border-left">
                        <div class="filter-dropdown">
                            <span class="filter-label">Category</span>
                            <select name="category" onchange="this.form.submit();">
                                <option value="all">All Categories</option>
                                <?php foreach($categories as $cat): if(!$cat) continue; ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= $category==$cat?'selected':'' ?>><?= ucfirst(htmlspecialchars($cat)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-group dropdown-group border-left">
                        <div class="filter-dropdown">
                            <span class="filter-label">Sort</span>
                            <select name="sort" onchange="this.form.submit();">
                                <option value="newest" <?= $sort=='newest'?'selected':'' ?>>Newest First</option>
                                <option value="oldest" <?= $sort=='oldest'?'selected':'' ?>>Oldest First</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <p class="showing-results-text">
            Showing <?= $totalArticles > 0 ? $offset + 1 : 0 ?>-<?= min($offset + $limit, $totalArticles) ?> of <?= number_format($totalArticles) ?> unique verified articles
        </p>


                <div class="journal-grid">
                    <style>
                        .journal-card { animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; transform: translateY(30px); }
                        <?php for($i=1; $i<=20; $i++) echo ".journal-card:nth-child($i) { animation-delay: " . ($i * 0.05) . "s; } \n"; ?>
                        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
                    </style>
                    
                    <?php foreach($journalEntries as $entry): 
                        // Determine colors and icons based on real DB verdict
                        $vLower = strtolower($entry['verdict']);
                        $vClass = ($vLower === 'fake') ? 'fake' : (($vLower === 'real') ? 'real' : 'unverified');
                        $icon = ($vLower === 'real') ? '✓' : (($vLower === 'fake') ? '✕' : '⚠');
                        
                        // Smart truncation: Create a title from the content and a snippet from the AI summary
                        $title = mb_substr(strip_tags($entry['content']), 0, 55) . '...';
                        $snippet = mb_substr(strip_tags($entry['ai_summary']), 0, 100) . '...';
                    ?>
                        <div class="journal-card">
                            <div class="j-card-header">
                                <span class="j-category text-cyan"><?= ucfirst(htmlspecialchars($entry['content_type'])) ?></span>
                                <span class="claim-badge <?= $vClass ?>"><?= $icon ?> <?= strtoupper($vLower) ?></span>
                            </div>
                            
                            <h3 class="j-card-title"><?= htmlspecialchars($title) ?></h3>
                            <p class="j-card-snippet"><?= htmlspecialchars($snippet) ?></p>
                            
                            <div class="j-card-footer">
                                <div class="j-meta">
                                    <span style="display:block; margin-bottom:4px; color: var(--text-secondary);"><?= date('M d, Y', strtotime($entry['analyzed_at'])) ?></span>
                                    <span class="text-green" style="font-weight:600; font-size: 0.75rem;">BlockChain Verified</span>
                                </div>
                                <a href="results.php?id=<?= $entry['id'] ?>" class="j-view-link">View Report ↗</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php 
            // Preserve search/filter parameters when changing pages
            $queryStr = $_GET; 
            unset($queryStr['page']); 
            $baseQuery = http_build_query($queryStr); 
            $baseUrl = "?$baseQuery&page="; 
            ?>
            <a href="<?= $page > 1 ? $baseUrl . ($page - 1) : '#' ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" style="text-decoration: none;">Previous</a>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?= $baseUrl . $i ?>" class="page-num <?= $i == $page ? 'active' : '' ?>" style="text-decoration: none;"><?= $i ?></a>
            <?php endfor; ?>
            
            <?php if ($page + 2 < $totalPages): ?>
                <span class="page-dots">...</span>
                <a href="<?= $baseUrl . $totalPages ?>" class="page-num" style="text-decoration: none;"><?= $totalPages ?></a>
            <?php endif; ?>
            
            <a href="<?= $page < $totalPages ? $baseUrl . ($page + 1) : '#' ?>" class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" style="text-decoration: none;">Next ↗</a>
        </div>
        <?php endif; ?>

    </div>
</main>

<?php require_once 'includes/footer.php'; ?>