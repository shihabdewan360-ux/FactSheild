<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/navbar.php'; ?>

<main class="discover-page">
    <div class="container">
        
        <div class="breadcrumbs">
            <a href="index.php">Home</a> > <span>Discover</span>
        </div>

        <div class="discover-header">
            <h1 class="landing-title">The Engine of <span class="text-cyan">Truth</span></h1>
            <p class="landing-subtitle" style="max-width: 600px; margin: 0 auto 48px;">
                FACTSHIELD combines cutting-edge Natural Language Processing with immutable Web3 ledger technology to create a trustless verification ecosystem.
            </p>
        </div>

        <div class="bento-grid">
            
            <div class="bento-card span-2 ai-glow">
                <div class="bento-icon">🧠</div>
                <h3 class="bento-title">Proprietary AI Analysis Engine</h3>
                <p class="bento-desc">
                    Our machine learning pipeline doesn't just read words; it understands context. By extracting specific claims from massive blocks of text, it cross-references assertions against thousands of verified medical, scientific, and global news databases in under 10 seconds.
                </p>
            </div>

            <div class="bento-card polygon-glow">
                <div class="bento-icon">⛓️</div>
                <h3 class="bento-title">Polygon Network Integration</h3>
                <p class="bento-desc">
                    Every verification is hashed and permanently minted to the Polygon blockchain, ensuring no government or entity can ever alter or delete the record.
                </p>
            </div>

            <div class="bento-card">
                <div class="bento-icon">🌍</div>
                <h3 class="bento-title">Global Threat Heatmap</h3>
                <p class="bento-desc">
                    We track the viral spread of misinformation in real-time. By categorizing fake news by region and topic (e.g., Politics, Health), we provide journalists with an early warning system.
                </p>
            </div>

            <div class="bento-card span-2">
                <div class="bento-icon">⚡</div>
                <h3 class="bento-title">Open Journal Ecosystem</h3>
                <p class="bento-desc">
                    Truth shouldn't be gatekept. The FACTSHIELD Journal serves as a publicly accessible, dynamically updated repository of all processed claims, completely open for researchers, educators, and the public.
                </p>
                <a href="verify.php" class="btn-primary" style="display: inline-block; margin-top: 24px;">Run a Verification ↗</a>
            </div>

        </div>

    </div>
</main>

<?php require_once 'includes/footer.php'; ?>