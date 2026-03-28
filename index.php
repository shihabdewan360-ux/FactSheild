<?php require_once 'includes/header.php'; ?>
<?php require_once 'includes/navbar.php'; ?>

<main>
    <section class="landing-hero">
        <div class="landing-glow"></div>
        
        <div class="landing-content">
            <h1 class="landing-title">Know What's Real.<br>In Seconds.</h1>
            <p class="landing-subtitle">Paste any article, URL or video. Get the truth instantly</p>
            
            <div class="landing-actions">
                <a href="verify.php" class="btn-primary">Get Started ↗</a>
                <a href="discover.php" class="btn-outline">Discover More</a>
            </div>
        </div>

        <div class="spline-container">
            <script type="module" src="https://unpkg.com/@splinetool/viewer@1.9.72/build/spline-viewer.js"></script>
            <spline-viewer url="https://prod.spline.design/j0J64GiBxLETrLXQ/scene.splinecode"></spline-viewer>
        </div>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>