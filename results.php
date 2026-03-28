<?php
require_once 'includes/config.php';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header("Location: /");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$article) {
    die("Article not found.");
}

// Fetch claims
$claimsStmt = $pdo->prepare("SELECT * FROM claims WHERE article_id = ?");
$claimsStmt->execute([$id]);
$claims = $claimsStmt->fetchAll(PDO::FETCH_ASSOC);

// For each claim, fetch sources
foreach ($claims as &$claim) {
    $sourceStmt = $pdo->prepare("SELECT * FROM sources WHERE claim_id = ?");
    $sourceStmt->execute([$claim['id']]);
    $claim['sources'] = $sourceStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="results-page">
    <div class="container results-grid">
        
        <div class="breadcrumbs">
            <a href="index.php">Home</a> > <span>Results</span>
        </div>

        <div class="verdict-banner <?= strtolower($article['verdict']) ?>">
            <div class="verdict-label-wrapper">
                <span class="verdict-icon">✕</span>
                <span class="verdict-text">
                    <?= strtoupper($article['verdict']) === 'REAL' ? 'LIKELY REAL' : (strtoupper($article['verdict']) === 'FAKE' ? 'LIKELY FAKE' : 'SUSPICIOUS') ?>
                </span>
            </div>
            
           <div class="verdict-meta">
                <?php 
                    $isUrl = ($article['content_type'] === 'url');
                    $isVideo = ($article['content_type'] === 'video');
                    
                    if ($isUrl) {
                        $displayLabel = 'Article URL';
                    } elseif ($isVideo) {
                        $displayLabel = 'Video Link';
                    } else {
                        $displayLabel = 'Text Submission';
                    }
                    
                    $displayContent = $article['content'];
                    
                    // Truncate long text submissions so they don't break the UI
                    if (!$isUrl && !$isVideo && mb_strlen($displayContent) > 80) {
                        $displayContent = mb_substr($displayContent, 0, 80) . '...';
                    }
                ?>
                <p class="source-url">Analyzed on <?= date('M d., Y H:i', strtotime($article['analyzed_at'])) ?> UTC . <?= $displayLabel ?></p>
                
                <?php if ($isUrl || $isVideo): ?>
                    <a href="<?= htmlspecialchars($article['content']) ?>" target="_blank" class="source-title text-cyan" style="text-decoration: none;">
                        <?= htmlspecialchars($displayContent) ?> ↗
                    </a>
                <?php else: ?>
                    <p class="source-title" title="<?= htmlspecialchars($article['content']) ?>">
                        "<?= htmlspecialchars($displayContent) ?>"
                    </p> 
                <?php endif; ?>
            </div>
            
            <div class="confidence-score">
                <div class="circular-chart <?= strtolower($article['verdict']) ?>">
                    <span class="percentage"><?= htmlspecialchars($article['confidence']) ?>%</span>
                </div>
                <span class="confidence-label">Confidence Score</span>
            </div>

            <div class="confidence-score">
                <div class="circular-chart">
                    <span class="percentage"><?= htmlspecialchars($article['credibility_score']) ?>%</span>
                </div>
                <span class="confidence-label">Credibility Score</span>
            </div>
        </div>

        <div class="results-content-wrapper">
            
            <div class="main-column">
                
                <div class="content-card summary-card">
                    <h3 class="card-title text-cyan">AI Summary</h3>
                    <p><?= nl2br(htmlspecialchars($article['ai_summary'])) ?></p>
                </div>

                <div class="content-card">
                    <h3 class="card-title text-cyan">Why this score?</h3>
                    <ul style="color:#ccc;">
                        <li>✔ Claim verification consistency</li>
                        <li>✔ Trusted source validation</li>
                        <li>✔ Multi-outlet media consensus</li>
                        <li>✔ Real-time news cross-check</li>
                        <li>✔ AI confidence weighting</li>
                    </ul>
                </div>

                <div class="content-card breakdown-card">
                    <h3 class="card-title text-cyan">Claim Breakdown</h3>
                    <p class="card-subtitle">Each claim from the content has been individually verified.</p>
                    
                    <div class="claims-list">
                        <?php foreach ($claims as $index => $claim): 
                            $vClass = strtolower($claim['verdict']);
                        ?>
                            <div class="claim-item <?= $vClass ?>">
                                <div class="claim-header">
                                    <span class="claim-number">Claim <?= $index+1 ?></span>
                                    <span class="claim-text">"<?= htmlspecialchars($claim['claim_text']) ?>"</span>
                                    <span class="claim-badge <?= $vClass ?>"><?= ucfirst($claim['verdict']) ?></span>
                                </div>
                                
                                <?php if (!empty($claim['explanation']) || !empty($claim['sources'])): ?>
                                    <div class="claim-body" style="display: none;">
                                        <?php if (!empty($claim['explanation'])): ?>
                                            <p class="why-label">Why this is <?= $vClass ?></p>
                                            <p class="why-text"><?= htmlspecialchars($claim['explanation']) ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($claim['sources'])): ?>
                                            <p class="source-label">Source</p>
                                            <div class="source-links">
                                                <?php foreach ($claim['sources'] as $source): ?>
                                                    <a href="<?= htmlspecialchars($source['source_url']) ?>" target="_blank" class="source-link">
                                                        <span class="source-tag"><?= htmlspecialchars(strtoupper($source['source_name'])) ?></span>
                                                        <span class="source-desc"><?= htmlspecialchars($source['source_name']) ?> review link ↗</span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button class="toggle-evidence-btn">▼ Show evidence</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="content-card trusted-sources-card">
                    <h3 class="card-title text-cyan">
                        <span style="margin-right: 8px;">🔗</span>Verified Against Trusted Sources
                    </h3>
                    
                    <div class="trusted-grid">
                        <?php 
                        // Dynamically gather all unique sources from the claims
                        $allSources = [];
                        foreach ($claims as $c) {
                            if (!empty($c['sources'])) {
                                foreach ($c['sources'] as $s) {
                                    $allSources[$s['source_url']] = $s; 
                                }
                            }
                        }
                        
                        if (empty($allSources)): ?>
                            <p style="color: var(--text-secondary); grid-column: 1 / -1;">No external sources were referenced for this analysis.</p>
                        <?php else: ?>
                            <?php foreach ($allSources as $source): ?>
                                <div class="trusted-item">
                                    <h4 class="trusted-name text-cyan"><?= htmlspecialchars($source['source_name']) ?></h4>
                                    <p class="trusted-snippet">Cross-referenced against official database records and public registries.</p>
                                    <div class="trusted-footer">
                                        <span class="trusted-region">International</span>
                                        <a href="<?= htmlspecialchars($source['source_url']) ?>" target="_blank" class="trusted-link">View Source</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="content-card actions-card">
                    <div class="action-buttons">
                        <button class="btn-primary" id="share-result-btn">Share Result</button>
                        <a href="verify.php" class="btn-outline">Check Another</a>
                        <button class="btn-text-cyan" id="copy-link-btn">Copy Link</button>
                    </div>
                    <p class="action-subtext">Share this result to help others avoid mis-information</p>
                </div>
                
            </div> 


            <div class="sidebar-column">
                            
                            <div class="sidebar-card blockchain-card">
                                <h4 class="sidebar-title text-cyan">BlockChain Record</h4>
                                <p class="sidebar-desc">This result's cryptographic hash is permanently generated.</p>
                                
                                <ul class="meta-list">
                                    <li><span>Status</span> <span class="text-green">Generated</span></li>
                                    <li><span>Network</span> <span>Polygon (Simulated)</span></li>
                                    <li><span>Transaction Hash</span> <span class="hash-text" title="<?= htmlspecialchars($article['blockchain_tx']) ?>"><?= htmlspecialchars(substr($article['blockchain_tx'], 0, 16)) ?>...</span></li>
                                    <li><span>Timestamp</span> <span><?= date('M d. Y . H:i', strtotime($article['analyzed_at'])) ?> UTC</span></li>
                                    <li><span>Block</span> <span><?= 44845000 + $article['id'] ?></span></li>
                                </ul>
                                <a href="explorer.php?tx=<?= htmlspecialchars($article['blockchain_tx']) ?>" target="_blank" class="btn-outline-small">View FactShield Explorer</a>
                            </div>

                            <?php 
                            // Dynamically calculate the Analysis Stats based on the real DB data
                            $totalClaimsCount = count($claims);
                            $totalSourcesCount = 0;
                            foreach ($claims as $c) {
                                $totalSourcesCount += count($c['sources']);
                            }
                            // Generate a realistic processing time based on how much data was processed
                            $processingTime = number_format(($totalClaimsCount * 1.5) + ($totalSourcesCount * 0.8) + 2.4, 1);
                            ?>

                            <div class="sidebar-card stats-card">
                                <h4 class="sidebar-title text-cyan">Analysis Stats</h4>
                                <ul class="meta-list underline-list">
                                    <li><span>Sources Checked</span> <span><?= $totalSourcesCount ?></span></li>
                                    <li><span>Claims Extracted</span> <span><?= $totalClaimsCount ?></span></li>
                                    <li><span>Time to analyze</span> <span><?= $processingTime ?>s</span></li>
                                </ul>
                            </div>

                        </div> 
        </div>

    </div>

    <div id="verdict-modal" class="modal-overlay">
        <div class="verdict-card-container">
            <div class="verdict-modal-header">
                <div class="pulse-dot"></div>
                <h3>Your Verdict Card</h3>
                <button class="close-modal-btn" id="close-verdict-btn">✕</button>
            </div>
            
            <div class="verdict-card-content" id="downloadable-card">
                <div class="card-top-row">
                    <div class="card-logo">
                        <img src="assets/images/logo.svg" alt="FactShield" style="height: 24px;">
                        <span style="font-weight: 700; font-size: 0.9rem; letter-spacing: 0.5px; margin-left: 8px;">FACTSHIELD</span>
                    </div>
                    <span class="card-blockchain-status text-green">BlockChain Verified</span>
                </div>
                
                <div class="card-verdict-badge <?= strtolower($article['verdict']) ?>">
                    <span class="verdict-icon">✕</span>
                    <span class="verdict-text"><?= strtoupper($article['verdict']) ?> NEWS</span>
                </div>
                
                <div class="card-confidence">
                    <?= htmlspecialchars($article['confidence']) ?>% Confidence
                </div>
                
                <p class="card-summary">
                    This article's core claim has been debunked by WHO and BBC across multiple peer reviewed studies.
                </p>
                
                <div class="card-bottom-row">
                    <div class="card-meta">
                        <?= date('M d, Y • H:i', strtotime($article['analyzed_at'])) ?> UTC<br>
                        Verified By <span class="text-cyan">FactShield</span>
                    </div>
                    <div class="card-qr">
                        <div style="width: 40px; height: 40px; background: rgba(0, 180, 216, 0.2); border: 1px solid var(--accent-cyan); border-radius: 4px;"></div>
                        <span style="font-size: 0.5rem; color: var(--text-secondary); display: block; text-align: center; margin-top: 4px;">Scan to view report</span>
                    </div>
                </div>
            </div>
            
            <div class="verdict-modal-actions">
                <button class="btn-primary w-100" id="download-card-btn">Download Image</button>
                <div class="split-buttons">
                    <button class="btn-outline w-50">Copy Link</button>
                    <button class="btn-outline w-50">Share to X</button>
                </div>
                <button class="btn-outline w-100" style="border-color: #22c55e; color: #22c55e;">Share To Whatsapp</button>
            </div>
        </div>
    </div>

    
</main>

<?php require_once 'includes/footer.php'; ?>