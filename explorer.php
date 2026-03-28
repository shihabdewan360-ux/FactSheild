<?php
require_once 'includes/config.php';

// Get the transaction hash from the URL
$txHash = isset($_GET['tx']) ? trim($_GET['tx']) : '';

if (!$txHash) {
    header("Location: index.php");
    exit;
}

// Fetch the article tied to this hash
$stmt = $pdo->prepare("SELECT * FROM articles WHERE blockchain_tx = ?");
$stmt->execute([$txHash]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    die("<div style='color:white; text-align:center; padding: 50px;'><h2>Transaction Not Found</h2><p>This hash does not exist in the FactShield ledger.</p></div>");
}

// Fetch claims to build the simulated "On-Chain Data Payload"
$claimsStmt = $pdo->prepare("SELECT claim_text, verdict FROM claims WHERE article_id = ?");
$claimsStmt->execute([$article['id']]);
$claims = $claimsStmt->fetchAll(PDO::FETCH_ASSOC);

// Create a simulated JSON payload that looks like Web3 contract data
$payloadData = [
    "method" => "recordVerification",
    "timestamp" => $article['analyzed_at'],
    "verdict" => strtoupper($article['verdict']),
    "confidence" => $article['confidence'] . "%",
    "verified_claims" => $claims
];
$jsonPayload = json_encode($payloadData, JSON_PRETTY_PRINT);

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .explorer-page { padding: 40px 20px 80px; max-width: 1000px; margin: 0 auto; }
    .explorer-header { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .explorer-title { font-size: 1.5rem; font-weight: 600; color: #fff; }
    .badge-polygon { background: rgba(130, 71, 229, 0.1); color: #8247e5; border: 1px solid rgba(130, 71, 229, 0.3); padding: 4px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
    
    /* Cascading Card Animations */
    .tx-card { background: var(--bg-card); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 0; margin-bottom: 24px; overflow: hidden; opacity: 0; animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .tx-card:nth-child(2) { animation-delay: 0.2s; }
    .tx-card:nth-child(3) { animation-delay: 0.4s; }
    
    .tx-row { display: grid; grid-template-columns: 250px 1fr; padding: 16px 24px; border-bottom: 1px solid rgba(255,255,255,0.05); align-items: flex-start; transition: background 0.3s ease; }
    .tx-row:hover { background: rgba(0, 180, 216, 0.03); }
    .tx-row:last-child { border-bottom: none; }
    
    .tx-label { color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 8px; }
    .tx-value { color: var(--text-primary); font-size: 0.95rem; word-break: break-all; display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
    
    /* Pulsing Success Badge */
    .status-success { display: inline-flex; align-items: center; gap: 6px; background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; position: relative; }
    .status-success::before { content: ''; position: absolute; left: 8px; top: 50%; transform: translateY(-50%); width: 6px; height: 6px; background: #22c55e; border-radius: 50%; animation: pulseGreen 2s infinite; }
    .status-success svg { display: none; /* Hide default icon to use the pulse dot */ }
    
    .hash-badge { background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 0.9rem; color: var(--accent-cyan); }
    .copy-btn { background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 4px; transition: color 0.2s; display: flex; align-items: center; }
    .copy-btn:hover { color: #fff; }
    
    /* Payload & Toggles */
    .payload-header { display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 8px; }
    .payload-toggles { display: flex; gap: 8px; }
    .payload-toggle { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--text-secondary); padding: 4px 12px; border-radius: 6px; font-size: 0.75rem; cursor: pointer; transition: 0.2s; }
    .payload-toggle.active { background: rgba(0, 180, 216, 0.15); color: var(--accent-cyan); border-color: rgba(0, 180, 216, 0.3); }
    
    .data-payload { background: #080b12; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 16px; font-family: monospace; font-size: 0.85rem; color: #a8b2d1; overflow-x: auto; width: 100%; white-space: pre-wrap; transition: opacity 0.3s ease; }
    
    @keyframes fadeUp { 0% { opacity: 0; transform: translateY(20px); } 100% { opacity: 1; transform: translateY(0); } }
    @keyframes pulseGreen { 0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); } 100% { box-shadow: 0 0 0 0 rgba(0,0,0,0); } }
    
    @media (max-width: 768px) { .tx-row { grid-template-columns: 1fr; gap: 8px; } }
</style>

<main class="explorer-page">
    <div class="explorer-header">
        <h1 class="explorer-title">Transaction Details</h1>
        <span class="badge-polygon">Polygon Amoy Testnet</span>
    </div>

    <div class="tx-card">
        <div class="tx-row">
            <div class="tx-label">Transaction Hash:</div>
            <div class="tx-value"><span class="hash-badge"><?= htmlspecialchars($article['blockchain_tx']) ?></span></div>
        </div>
        <div class="tx-row">
            <div class="tx-label">Status:</div>
            <div class="tx-value">
                <span class="status-success">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    Success
                </span>
            </div>
        </div>
        <div class="tx-row">
            <div class="tx-label">Block:</div>
            <div class="tx-value"><span class="text-cyan"><?= 44845000 + $article['id'] ?></span> <span style="color:var(--text-secondary); font-size:0.8rem; margin-left:8px;"><span id="live-blocks">120</span> Block Confirmations</span></div>
        </div>
        <div class="tx-row">
            <div class="tx-label">Timestamp:</div>
            <div class="tx-value">
                ⏱ <?= date('Y-m-d H:i:s', strtotime($article['analyzed_at'])) ?> UTC 
            </div>
        </div>
    </div>

    <div class="tx-card">
        <div class="tx-row">
            <div class="tx-label">From (System Relayer):</div>
            <div class="tx-value"><span class="text-cyan">0x7F8Ea4d...</span></div>
        </div>
        <div class="tx-row">
            <div class="tx-label">Interacted With (To):</div>
            <div class="tx-value">Contract <span class="text-cyan">FactShieldRegistry</span> <span class="hash-badge">0xFaC7...0001</span></div>
        </div>
        <div class="tx-row">
            <div class="tx-label">Transaction Fee:</div>
            <div class="tx-value">0.00241 MATIC <span style="color:var(--text-secondary); font-size:0.8rem;">($0.00)</span></div>
        </div>
        <div class="tx-row">
            <div class="tx-label">Input Data:</div>
            <div class="tx-value" style="flex-direction: column; align-items: flex-start;">
                <div class="payload-header">
                    <span style="font-size: 0.85rem; color: var(--text-secondary);">Decoded FactShield Contract Payload</span>
                    <div class="payload-toggles">
                        <button class="payload-toggle active" id="btn-json">JSON</button>
                        <button class="payload-toggle" id="btn-hex">Raw Hex</button>
                    </div>
                </div>
                <div class="data-payload" id="payload-display"><?= htmlspecialchars($jsonPayload) ?></div>
            </div>
        </div>
    </div>
</main>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Live Block Confirmation Ticker (Simulates Polygon's ~2.1s block time)
    const blockSpan = document.getElementById('live-blocks');
    let blocks = parseInt(blockSpan.innerText);
    
    setInterval(() => {
        blocks++;
        blockSpan.innerText = blocks;
        blockSpan.style.color = "var(--accent-green)"; // Flash green slightly
        setTimeout(() => { blockSpan.style.color = "inherit"; }, 500);
    }, 2500 + Math.random() * 1000); // Randomize slightly between 2.5s and 3.5s for realism

    // 2. Hex / JSON Toggle Logic
    const btnJson = document.getElementById('btn-json');
    const btnHex = document.getElementById('btn-hex');
    const display = document.getElementById('payload-display');
    
    // Store original JSON from the DOM
    const rawJson = display.innerText;
    
    // Create a fake hex string representation of the JSON for visual effect
    const fakeHex = "0x" + Array.from(rawJson).map(c => c.charCodeAt(0).toString(16)).join('').substring(0, 500) + "... [TRUNCATED FOR VIEWING]";

    btnJson.addEventListener('click', () => {
        btnHex.classList.remove('active');
        btnJson.classList.add('active');
        display.style.opacity = 0;
        setTimeout(() => {
            display.innerText = rawJson;
            display.style.opacity = 1;
        }, 200);
    });

    btnHex.addEventListener('click', () => {
        btnJson.classList.remove('active');
        btnHex.classList.add('active');
        display.style.opacity = 0;
        setTimeout(() => {
            display.innerText = fakeHex;
            display.style.opacity = 1;
            display.style.wordBreak = "break-all";
        }, 200);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>