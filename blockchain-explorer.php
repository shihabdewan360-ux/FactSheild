<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/navbar.php'; ?>

<?php
require_once 'includes/config.php'; // Connect to the database

// 1. Fetch real stats from the database
$totalStmt = $pdo->query("SELECT COUNT(*) FROM articles WHERE blockchain_tx IS NOT NULL");
$totalVerifications = $totalStmt->fetchColumn();

// 2. Fetch the latest 15 transactions
$txStmt = $pdo->query("SELECT id, blockchain_tx, verdict, analyzed_at FROM articles WHERE blockchain_tx IS NOT NULL ORDER BY analyzed_at DESC LIMIT 15");
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

// Simulate a realistic starting block number for Polygon
$baseBlock = 448452900;
$latestBlock = $baseBlock + (count($transactions) > 0 ? $transactions[0]['id'] : 0);

// Top Stats
$networkStats = [
    ['label' => 'Network', 'value' => 'Polygon Mainnet'],
    ['label' => 'Total Verifications', 'value' => number_format($totalVerifications + 21200)], // Added base to match your UI
    ['label' => 'Latest Block', 'value' => '<span id="live-block">' . $latestBlock . '</span>'],
];
?>

<main class="explorer-page">
    <div class="container">
        
        <div class="breadcrumbs">
            <a href="index.php">Home</a> > <span>Blockchain Explorer</span>
        </div>

        <div class="explorer-header">
            <div>
                <h1 class="landing-title" style="font-size: 3rem; margin-bottom: 8px;">Immutable Ledger</h1>
                <p class="landing-subtitle" style="margin-bottom: 0;">
                    <span class="live-dot-small" style="background-color: var(--accent-green); box-shadow: 0 0 10px var(--accent-green); animation: pulseGreen 2s infinite;"></span>
                    Live Polygon Network Status
                </p>
            </div>
            
            <div class="search-input-wrapper explorer-search">
                <span style="opacity: 0.5;">🔍</span>
                <input type="text" placeholder="Search by Txn Hash / Block / Article ID" class="journal-search">
            </div>
        </div>

        <div class="heatmap-stats-bar content-card" style="margin-bottom: 32px; grid-template-columns: repeat(3, 1fr);">
            <?php foreach($networkStats as $index => $stat): ?>
                <div class="h-stat-item <?= $index !== count($networkStats)-1 ? 'border-right' : '' ?>" style="padding: 24px;">
                    <p class="h-stat-label"><?= $stat['label'] ?></p>
                    <h3 class="h-stat-value text-cyan" style="font-size: 1.5rem;"><?= $stat['value'] ?></h3>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="content-card table-card">
            <div class="table-header-bar">
                <h3 class="card-title text-cyan" style="margin-bottom: 0;">Latest Transactions</h3>
                <button class="btn-outline-small">View All</button>
            </div>
            
            <div class="table-responsive">
                <table class="explorer-table">
                    <thead>
                        <tr>
                            <th>Txn Hash</th>
                            <th>Block</th>
                            <th>Age</th>
                            <th>Action</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $delay = 0;
                        foreach($transactions as $txn): 
                            $delay += 0.05; // Smooth cascading animation
                            $blockNum = $baseBlock + $txn['id'];
                            $vColor = strtolower($txn['verdict']) == 'real' ? 'real' : (strtolower($txn['verdict']) == 'fake' ? 'fake' : 'suspicious');
                        ?>
                            <tr style="animation-delay: <?= $delay ?>s; opacity: 0; animation: fadeUp 0.5s forwards;">
                                <td>
                                    <div class="txn-cell">
                                        <span class="txn-icon">📄</span>
                                        <a href="tx.php?tx=<?= htmlspecialchars($txn['blockchain_tx']) ?>" class="txn-hash">
                                            <?= substr($txn['blockchain_tx'], 0, 15) ?>...
                                        </a>
                                    </div>
                                </td>
                                <td style="font-family: monospace; color: var(--text-primary);"><?= $blockNum ?></td>
                                <td><span class="tx-age" data-timestamp="<?= strtotime($txn['analyzed_at']) ?>" style="color: var(--text-secondary);">Calculating...</span></td>
                                <td><span class="action-badge">Verify Fact</span></td>
                                <td>
                                    <span class="claim-badge <?= $vColor ?>" style="display: inline-block; text-align: center; min-width: 90px;">
                                        <?= ucfirst($txn['verdict']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Live Block Ticker (Polygon generates a block ~every 2.1 seconds)
    const blockEl = document.getElementById('live-block');
    if(blockEl) {
        let currentBlock = parseInt(blockEl.innerText.replace(/,/g, ''));
        setInterval(() => {
            currentBlock++;
            blockEl.innerText = currentBlock;
            // Subtle green flash effect
            blockEl.style.color = "var(--accent-green)"; 
            blockEl.style.textShadow = "0 0 10px rgba(34, 197, 94, 0.5)";
            setTimeout(() => { 
                blockEl.style.color = "inherit"; 
                blockEl.style.textShadow = "none"; 
            }, 400);
        }, 2100);
    }

    // 2. Live "Age" Ticker
    const ageCells = document.querySelectorAll('.tx-age');
    function updateAges() {
        const now = Math.floor(Date.now() / 1000); // Current UTC timestamp
        ageCells.forEach(cell => {
            const txTime = parseInt(cell.getAttribute('data-timestamp'));
            const diff = now - txTime;
            
            if (diff < 60) cell.innerText = Math.max(1, diff) + " secs ago";
            else if (diff < 3600) cell.innerText = Math.floor(diff/60) + " mins ago";
            else if (diff < 86400) cell.innerText = Math.floor(diff/3600) + " hrs ago";
            else cell.innerText = Math.floor(diff/86400) + " days ago";
        });
    }
    
    // Run immediately, then update every 1 second
    updateAges();
    setInterval(updateAges, 1000);
});
</script>

<?php require_once 'includes/footer.php'; ?>