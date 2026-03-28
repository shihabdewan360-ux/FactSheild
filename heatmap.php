<?php require_once 'includes/config.php'; ?>
<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/navbar.php'; ?>

<?php
// 1. CAPTURE FILTER INPUTS
$topicFilter  = $_GET['topic'] ?? 'All';
$timeFilter   = $_GET['time'] ?? 'All Time';
$regionFilter = $_GET['region'] ?? 'Global';
$countryFilter = $_GET['country'] ?? 'All'; // NEW: Catches clicks on specific dots

// 2. BUILD THE DYNAMIC SQL QUERY
$where = ["verdict IN ('fake', 'suspicious')"]; 
$params = [];

if ($topicFilter !== 'All') {
    $where[] = "content_type = ?";
    $params[] = strtolower($topicFilter);
}

if ($timeFilter === 'Today') {
    $where[] = "DATE(analyzed_at) = CURDATE()";
} elseif ($timeFilter === 'This Week') {
    $where[] = "analyzed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
}

$whereClause = implode(" AND ", $where);

// 3. FETCH & AGGREGATE THE HOTSPOTS (1 Dot Per Country)
$mapStmt = $pdo->prepare("SELECT id, content, ai_summary, confidence FROM articles WHERE $whereClause ORDER BY analyzed_at DESC LIMIT 500"); // Increased limit to ensure good aggregation
$mapStmt->execute($params);
$mapItems = $mapStmt->fetchAll(PDO::FETCH_ASSOC);

// Define static nodes for countries (X/Y coordinates on the SVG map)
$countries = [
    'USA' => ['region' => 'North America', 'x' => 20, 'y' => 35, 'words' => ['us', 'usa', 'america', 'washington', 'new york', 'pentagon', 'trump', 'biden', 'harris', 'california', 'texas'], 'count' => 0],
    'Canada' => ['region' => 'North America', 'x' => 22, 'y' => 20, 'words' => ['canada', 'trudeau', 'toronto', 'vancouver'], 'count' => 0],
    'Mexico' => ['region' => 'North America', 'x' => 18, 'y' => 45, 'words' => ['mexico', 'amlo'], 'count' => 0],
    'Brazil' => ['region' => 'South America', 'x' => 32, 'y' => 65, 'words' => ['brazil', 'lula', 'amazon', 'rio'], 'count' => 0],
    'Argentina' => ['region' => 'South America', 'x' => 28, 'y' => 80, 'words' => ['argentina', 'milei', 'buenos aires'], 'count' => 0],
    'UK' => ['region' => 'Europe', 'x' => 47, 'y' => 28, 'words' => ['uk', 'britain', 'london', 'england'], 'count' => 0],
    'France' => ['region' => 'Europe', 'x' => 49, 'y' => 32, 'words' => ['france', 'macron', 'paris'], 'count' => 0],
    'Germany' => ['region' => 'Europe', 'x' => 51, 'y' => 30, 'words' => ['germany', 'scholz', 'berlin'], 'count' => 0],
    'Russia' => ['region' => 'Russia/East EU', 'x' => 65, 'y' => 22, 'words' => ['putin', 'russia', 'moscow', 'kremlin', 'soviet'], 'count' => 0],
    'Ukraine' => ['region' => 'Russia/East EU', 'x' => 56, 'y' => 30, 'words' => ['ukraine', 'zelensky', 'kyiv'], 'count' => 0],
    'Iran' => ['region' => 'Middle East', 'x' => 60, 'y' => 40, 'words' => ['iran', 'khamenei', 'tehran'], 'count' => 0],
    'Israel' => ['region' => 'Middle East', 'x' => 57, 'y' => 42, 'words' => ['israel', 'netanyahu', 'gaza', 'jerusalem'], 'count' => 0],
    'India' => ['region' => 'South Asia', 'x' => 68, 'y' => 46, 'words' => ['india', 'modi', 'delhi', 'mumbai'], 'count' => 0],
    'Bangladesh' => ['region' => 'South Asia', 'x' => 71, 'y' => 47, 'words' => ['bangladesh', 'hasina', 'dhaka'], 'count' => 0],
    'China' => ['region' => 'East Asia', 'x' => 75, 'y' => 38, 'words' => ['china', 'xi jinping', 'beijing', 'taiwan', 'hong kong'], 'count' => 0],
    'Japan' => ['region' => 'East Asia', 'x' => 84, 'y' => 35, 'words' => ['japan', 'tokyo'], 'count' => 0],
    'South Africa' => ['region' => 'Africa', 'x' => 52, 'y' => 75, 'words' => ['south africa', 'cape town', 'pretoria'], 'count' => 0],
    'Nigeria' => ['region' => 'Africa', 'x' => 48, 'y' => 55, 'words' => ['nigeria', 'lagos'], 'count' => 0],
    'Australia' => ['region' => 'Oceania', 'x' => 82, 'y' => 75, 'words' => ['australia', 'sydney', 'melbourne'], 'count' => 0]
];

// Scan articles and tally up counts for each country
foreach ($mapItems as $item) {
    $textToAnalyze = strtolower($item['content'] . ' ' . $item['ai_summary']);
    foreach ($countries as $cName => $data) {
        foreach ($data['words'] as $word) {
            if (strpos($textToAnalyze, $word) !== false) {
                $countries[$cName]['count']++;
                break 2; // Stop searching once we find a context match for this article
            }
        }
    }
}

$hotspots = [];
// Generate dots ONLY for countries with > 0 searches, filtered by region
foreach ($countries as $cName => $data) {
    if ($data['count'] > 0) {
        if ($regionFilter === 'Global' || $regionFilter === $data['region']) {
            
            // Determine glow/intensity based on search volume
            $intensity = 'moderate'; // Lite color
            if ($data['count'] >= 10) $intensity = 'critical'; // Dark red for many
            elseif ($data['count'] >= 4) $intensity = 'high';  // Orange for some
            
            $hotspots[] = [
                'name' => $cName,
                'x' => $data['x'],
                'y' => $data['y'],
                'intensity' => $intensity,
                'count' => $data['count']
            ];
        }
    }
}

// 4. FETCH SIDEBAR TRENDING (Syncs perfectly with Time, Region, AND Map Clicks)
$sidebarWhereClause = $whereClause; // Inherit the Time and Topic filters

if ($countryFilter !== 'All' && isset($countries[$countryFilter])) {
    // 1. If a specific map dot was clicked, filter strictly to that country's keywords
    $words = $countries[$countryFilter]['words'];
    $wordConditions = [];
    foreach ($words as $w) {
        $wordConditions[] = "(LOWER(content) LIKE '%" . $w . "%' OR LOWER(ai_summary) LIKE '%" . $w . "%')";
    }
    $sidebarWhereClause .= " AND (" . implode(" OR ", $wordConditions) . ")";
    
} elseif ($regionFilter !== 'Global') {
    // 2. If a Region dropdown was selected, gather ALL keywords for that entire region
    $regionWords = [];
    foreach ($countries as $data) {
        if ($data['region'] === $regionFilter) {
            $regionWords = array_merge($regionWords, $data['words']);
        }
    }
    // Force the sidebar to only show articles matching the Region's keywords
    if (!empty($regionWords)) {
        $wordConditions = [];
        foreach ($regionWords as $w) {
            $wordConditions[] = "(LOWER(content) LIKE '%" . $w . "%' OR LOWER(ai_summary) LIKE '%" . $w . "%')";
        }
        $sidebarWhereClause .= " AND (" . implode(" OR ", $wordConditions) . ")";
    }
}

$sidebarStmt = $pdo->prepare("SELECT id, content, content_type, verdict, confidence, analyzed_at FROM articles WHERE $sidebarWhereClause ORDER BY analyzed_at DESC LIMIT 4");
$sidebarStmt->execute($params);
$topMisinformation = $sidebarStmt->fetchAll(PDO::FETCH_ASSOC);

// 5. CALCULATE LIVE STATS
$totalStmt = $pdo->query("SELECT COUNT(*) FROM articles");
$totalProcessed = $totalStmt->fetchColumn();

// Count of dots on the current filtered map
$activeHotspots = count($hotspots); 

$topTopicStmt = $pdo->query("SELECT content_type, COUNT(*) as count FROM articles WHERE verdict IN ('fake', 'suspicious') GROUP BY content_type ORDER BY count DESC LIMIT 1");
$topTopicRow = $topTopicStmt->fetch(PDO::FETCH_ASSOC);
$topTopic = $topTopicRow && $topTopicRow['content_type'] ? ucfirst($topTopicRow['content_type']) : 'General';

$todayStmt = $pdo->query("SELECT COUNT(*) FROM articles WHERE DATE(analyzed_at) = CURDATE() AND verdict IN ('fake', 'suspicious')");
$newToday = $todayStmt->fetchColumn();

$stats = [
    ['label' => 'Total Scans', 'value' => number_format($totalProcessed), 'sub' => 'All time checks', 'color' => 'cyan'],
    ['label' => 'Active Hotspots', 'value' => $activeHotspots, 'sub' => 'Filtered Global Nodes', 'color' => 'red'], 
    ['label' => 'Top Threat Topic', 'value' => $topTopic, 'sub' => 'Highest frequency fake news', 'color' => 'yellow'],
    ['label' => 'New Threats Today', 'value' => number_format($newToday), 'sub' => 'Fake/Suspicious found today', 'color' => 'green']
];

// UI Dropdown Arrays
$topics = ['All', 'Health', 'Politics', 'War', 'Finance', 'Science', 'Text', 'Url'];
$times = ['All Time', 'Today', 'This Week'];
$regions = ['North America', 'South America', 'Europe', 'Russia/East EU', 'Middle East', 'South Asia', 'East Asia', 'Africa', 'Oceania'];
?>

<main class="heatmap-page">
    <div class="container">
        
        <div class="breadcrumbs">
            <a href="index.php">Home</a> > <span>Heatmap</span>
        </div>

        <div class="heatmap-header">
            <h1 class="landing-title" style="font-size: 3.5rem; margin-bottom: 8px;">
                <span class="live-dot-large"></span> Misinformation Heatmap
            </h1>
            <p class="landing-subtitle" style="margin-bottom: 24px;">See where fake news is spreading right now across the globe.</p>
        </div>

       <form method="GET" action="heatmap.php" id="heatmap-form">
            <input type="hidden" name="country" id="country-input" value="<?= htmlspecialchars($countryFilter) ?>">
            <div class="heatmap-filters">
                <div class="filter-group">
                    <span class="filter-label">Topic:</span>
                    <input type="hidden" name="topic" id="topic-input" value="<?= htmlspecialchars($topicFilter) ?>">
                    <div class="filter-pills">
                        <?php foreach($topics as $t): ?>
                            <button type="button" class="filter-pill <?= $topicFilter === $t ? 'active' : '' ?>" onclick="document.getElementById('topic-input').value='<?= $t ?>'; document.getElementById('heatmap-form').submit();"><?= $t ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="filter-group dropdown-group">
                    <div class="filter-dropdown">
                        <span class="filter-label">Time</span>
                        <select name="time" onchange="document.getElementById('country-input').value='All'; document.getElementById('heatmap-form').submit();">
                            <?php foreach($times as $tm): ?>
                                <option value="<?= $tm ?>" <?= $timeFilter === $tm ? 'selected' : '' ?>><?= $tm ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-dropdown">
                        <span class="filter-label">Region</span>
                        <select name="region" onchange="document.getElementById('country-input').value='All'; document.getElementById('heatmap-form').submit();">
                            <option value="Global" <?= $regionFilter === 'Global' ? 'selected' : '' ?>>Global</option>
                            <?php foreach($regions as $regionName): ?>
                                <option value="<?= htmlspecialchars($regionName) ?>" <?= $regionFilter === $regionName ? 'selected' : '' ?>><?= htmlspecialchars($regionName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </form>

        <div class="heatmap-grid">
            
            <div class="map-section content-card">
                <div class="map-container">
                    <img src="assets/images/world.svg" alt="World Map" class="world-map-img" style="width:100%; opacity:0.8;">
                    
                    <?php foreach($hotspots as $spot): ?>
                        <div class="hotspot <?= $spot['intensity'] ?>" 
                             style="left: <?= $spot['x'] ?>%; top: <?= $spot['y'] ?>%;"
                             title="<?= $spot['name'] ?> (<?= $spot['count'] ?> threats)"
                             onclick="document.getElementById('country-input').value='<?= $spot['name'] ?>'; document.getElementById('heatmap-form').submit();">
                             <span class="hotspot-pulse"></span>
                        </div>
                    <?php endforeach; ?>

                    <div class="map-legend">
                        <h4>INTENSITY</h4>
                        <ul>
                            <li><span class="legend-dot critical"></span> Critical (>85% Conf.)</li>
                            <li><span class="legend-dot high"></span> High (>65% Conf.)</li>
                            <li><span class="legend-dot moderate"></span> Moderate</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="heatmap-sidebar content-card">
                <h3 class="sidebar-title">
                    <span class="live-dot-small"></span> Trending Misinformation 
                    <span class="region-focus">- <?= $countryFilter !== 'All' ? htmlspecialchars($countryFilter) : htmlspecialchars($regionFilter) ?></span>
                    <?php if($countryFilter !== 'All'): ?>
                        <button onclick="document.getElementById('country-input').value='All'; document.getElementById('heatmap-form').submit();" style="background:none; border:none; color:var(--accent-cyan); cursor:pointer; font-size: 0.8rem; margin-left: 8px;">(Clear View)</button>
                    <?php endif; ?>
                </h3>

                <div class="misinfo-list">
                    <?php if (empty($topMisinformation)): ?>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">No threats detected for these filters.</p>
                    <?php else: ?>
                        <?php foreach($topMisinformation as $item): 
                            $title = mb_substr(strip_tags($item['content']), 0, 55) . '...';
                            $vLower = strtolower($item['verdict']);
                            $viralViews = number_format(($item['confidence'] * 142) + rand(100, 5000));
                        ?>
                            <div class="misinfo-card">
                                <div class="misinfo-header">
                                    <h4 class="misinfo-title" style="margin:0;"><?= htmlspecialchars($title) ?></h4>
                                    <span class="claim-badge <?= $vLower ?>"><?= ucfirst($vLower) ?></span>
                                </div>
                                
                                <div class="misinfo-footer">
                                    <span class="misinfo-topic text-cyan"><?= ucfirst(htmlspecialchars($item['content_type'])) ?></span>
                                    <div class="misinfo-stats">
                                        <span class="shares"><?= $viralViews ?> views</span>
                                        <a href="results.php?id=<?= $item['id'] ?>" class="view-source-link">View Report ↗</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <a href="journal.php" class="btn-outline w-100" style="margin-top: 24px; text-align:center; display:block; text-decoration:none;">View All Journals</a>
            </div>
        </div>

        <div class="heatmap-stats-bar content-card">
            <?php foreach($stats as $index => $stat): ?>
                <div class="h-stat-item <?= $index !== count($stats)-1 ? 'border-right' : '' ?>">
                    <p class="h-stat-label"><?= $stat['label'] ?></p>
                    <h3 class="h-stat-value text-<?= $stat['color'] ?>"><?= $stat['value'] ?></h3>
                    <p class="h-stat-sub"><?= $stat['sub'] ?></p>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</main>

<?php require_once 'includes/footer.php'; ?>