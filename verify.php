<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/navbar.php'; ?>

<main>
    <section id="analyze" class="hero-section" style="padding-top: 40px; min-height: auto;">
        <div class="hero-content">
            <div class="badge-outline">
                <span>AI & Blockchain Powered Fact Checking</span>
            </div>
            
            <h2 class="hero-title" style="font-size: 3.5rem;">Stop Sharing, Start <span class="text-cyan">Verifying</span></h2>
            <p class="hero-subtitle">Paste any news article, URL, or video link. Our AI detects misinformation in seconds — verified forever on the blockchain.</p>
            
            <form action="analyze.php" method="POST" class="analysis-container">
                <div class="input-tabs">
                    <button type="button" class="tab-btn active" data-type="text">Text</button>
                    <button type="button" class="tab-btn" data-type="url">Article URL</button>
                    <button type="button" class="tab-btn" data-type="video">Video Link</button>
                </div>
                
                <div class="input-wrapper">
                    <input type="text" name="content" id="content" placeholder="Paste your URL/Link" required>
                </div>
                
                <div class="action-wrapper">
                    <button type="submit" class="btn-primary analyze-btn">Analyze Now ↗</button>
                </div>
                
                <input type="hidden" name="type" id="type" value="text">
            </form>
        </div>
    </section>

    <section class="how-it-works-section">
        <h3 class="subtitle-small">HOW IT WORKS</h3>
        <h2 class="title-large">Three steps to the truth</h2>

        <div class="steps-grid">
            <div class="step-card">
                <div class="step-icon-wrapper">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--accent-cyan)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 20px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </div>
                <h4>Step 01<br>Submit Content</h4>
                <p>Paste text, drop an article URL, or share a video link. Works with any format.</p>
            </div>

            <div class="step-card">
                <div class="step-icon-wrapper">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--accent-cyan)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 20px;"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>
                </div>
                <h4>Step 02<br>AI Analyzes</h4>
                <p>Our AI reads every claim, cross-checks against trusted global sources, and scores the content.</p>
            </div>

            <div class="step-card">
                <div class="step-icon-wrapper">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--accent-cyan)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 20px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
                </div>
                <h4>Step 03<br>Verified Forever</h4>
                <p>Your result is stored permanently on the blockchain. Tamper-proof. Shareable. Public.</p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number cyan">• 12,847</div>
                <div class="stat-label">Article verified</div>
            </div>
            <div class="stat-item border-left-right">
                <div class="stat-number red">• 10,768</div>
                <div class="stat-label">Fake News Caught</div>
            </div>
            <div class="stat-item">
                <div class="stat-number green">8,391</div>
                <div class="stat-label">BlockChain Records Created</div>
            </div>
        </div>
    </section>

    <div id="processing-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="pulse-dot"></div>
                <h3>Analyzing Content</h3>
                <span class="status-badge">In Progress</span>
            </div>
            
            <div class="modal-url-display">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.6;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                <span id="display-url-text">https://example.com/article/...</span>
                <span class="url-badge">URL</span>
            </div>

            <div class="progress-steps">
                <div class="step-item" id="step-1">
                    <div class="step-icon"></div>
                    <span class="step-text">Fetching content</span>
                    <span class="step-status">Wait</span>
                </div>
                <div class="step-item" id="step-2">
                    <div class="step-icon"></div>
                    <span class="step-text">Extracting claims</span>
                    <span class="step-status">Wait</span>
                </div>
                <div class="step-item" id="step-3">
                    <div class="step-icon"></div>
                    <span class="step-text">Cross referencing sources</span>
                    <span class="step-status">Wait</span>
                </div>
                <div class="step-item" id="step-4">
                    <div class="step-icon loader-icon"></div>
                    <span class="step-text">Recording to blockchain</span>
                    <span class="step-status">Wait</span>
                </div>
            </div>

            <div class="progress-footer">
                <p>Storing your result permanently on Polygon...</p>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" id="main-progress-bar"></div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>