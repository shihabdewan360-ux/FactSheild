document.addEventListener('DOMContentLoaded', function() {

    // --- Custom Toast Notification Function ---
    function showErrorToast(message) {
        // Remove existing toast if user clicks multiple times
        const existingToast = document.getElementById('custom-error-toast');
        if (existingToast) existingToast.remove();

        // Create the modern toast element
        const toast = document.createElement('div');
        toast.id = 'custom-error-toast';
        toast.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; vertical-align: middle;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg> ${message}`;
        
        // Apply trendy, modern styling directly
        Object.assign(toast.style, {
            position: 'fixed',
            top: '20px',
            left: '50%',
            transform: 'translate(-50%, -20px)',
            backgroundColor: '#ff4d4f',
            color: '#ffffff',
            padding: '12px 24px',
            borderRadius: '50px',
            boxShadow: '0 8px 16px rgba(255, 77, 79, 0.25)',
            fontWeight: '500',
            fontSize: '14px',
            zIndex: '9999',
            opacity: '0',
            display: 'flex',
            alignItems: 'center',
            transition: 'all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55)' // Bouncy, smooth animation
        });

        document.body.appendChild(toast);

        // Trigger the slide-in animation
        requestAnimationFrame(() => {
            toast.style.transform = 'translate(-50%, 20px)';
            toast.style.opacity = '1';
        });

        // Trigger the fade-out animation after 3.5 seconds
        setTimeout(() => {
            toast.style.transform = 'translate(-50%, -20px)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 400); // Remove from DOM after fade
        }, 3500);
    }
    // ------------------------------------------

    
    // Tab switching
    const tabs = document.querySelectorAll('.tab-btn');
    const typeInput = document.getElementById('type');
    const contentInput = document.getElementById('content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            typeInput.value = this.dataset.type;

            // Change placeholder based on tab
            const type = this.dataset.type;
            if (type === 'text') {
                contentInput.placeholder = 'Paste article text here...';
            } else if (type === 'url') {
                contentInput.placeholder = 'Paste article URL here...';
            } else if (type === 'video') {
                contentInput.placeholder = 'Paste video link (YouTube, etc.)...';
            }
        });
    });




// --- Processing Modal Logic ---
    const analysisForm = document.querySelector('.analysis-container');
    const modal = document.getElementById('processing-modal');
    const urlDisplayText = document.getElementById('display-url-text');
    const mainProgressBar = document.getElementById('main-progress-bar');
    
    if (analysisForm) {
        analysisForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Stop normal submission

            const inputValue = document.getElementById('content').value.trim();
            const typeValue = document.getElementById('type').value;

            // --- FRONTEND VALIDATION ---
            // Stricter check: Ensures the entire string is a valid URL without random text or spaces
            const isUrl = /^(https?:\/\/)?(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)$/i.test(inputValue);
            
            // Check if it's a known video platform
            const isVideo = isUrl && /(youtube\.com|youtu\.be|tiktok\.com|vimeo\.com|dailymotion\.com|rumble\.com)/i.test(inputValue);

            let errorMessage = "";

            if (typeValue === 'text' && isUrl) {
                errorMessage = 'Please use the "Article URL" or "Video Link" tab to analyze web links.';
            } else if (typeValue === 'url' && isVideo) {
                errorMessage = 'Please use the "Video Link" tab to analyze videos.';
            } else if (typeValue === 'url' && !isUrl) {
                errorMessage = 'Please enter a valid web URL (e.g., https://example.com). Plain text is not allowed here.';
            } else if (typeValue === 'video' && !isVideo) {
                errorMessage = 'Please enter a valid video URL (e.g., YouTube, TikTok). Plain text or standard articles are not allowed here.';
            }

            if (errorMessage) {
                // UI Enhancement: Smooth error shake animation
                const inputField = document.getElementById('content');
                inputField.animate([
                    { transform: 'translateX(0)' },
                    { transform: 'translateX(-8px)' },
                    { transform: 'translateX(8px)' },
                    { transform: 'translateX(-8px)' },
                    { transform: 'translateX(8px)' },
                    { transform: 'translateX(0)' }
                ], { duration: 400, easing: 'ease-in-out' });
                
                // Add a red border temporarily for visual feedback
                inputField.style.transition = 'border-color 0.3s ease';
                const originalBorder = inputField.style.borderColor;
                inputField.style.borderColor = '#ff4d4f';
                setTimeout(() => { inputField.style.borderColor = originalBorder; }, 2000);

                showErrorToast(errorMessage);
                return; // Stop execution
            }
            // ---------------------------

            urlDisplayText.textContent = inputValue ? inputValue : "Analyzing content...";
            modal.classList.add('active'); // Show modal

            // 1. START REAL BACKEND REQUEST IMMEDIATELY
            const formData = new FormData(analysisForm);
            let aiCompleted = false;
            let targetUrl = '';

            fetch('analyze.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if(response.redirected) {
                    targetUrl = response.url;
                    aiCompleted = true; // Flag that the AI is finished!
                } else {
                    // If the backend returns an error instead of a redirect
                    throw new Error('Backend validation failed or returned an error.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("Analysis failed. Please ensure the link is publicly accessible and try again.");
                modal.classList.remove('active');
            });

            // 2. RUN UI ANIMATION WHILE WAITING
            const steps = ['step-1', 'step-2', 'step-3', 'step-4'];
            let currentStep = 0;

            function advanceStep() {
                if (aiCompleted) {
                    // If AI finishes early, jump straight to the results page!
                    window.location.href = targetUrl;
                    return;
                }

                if (currentStep < steps.length) {
                    // Mark previous step as done
                    if (currentStep > 0) {
                        const prev = document.getElementById(steps[currentStep - 1]);
                        prev.classList.remove('active');
                        prev.classList.add('completed');
                        prev.querySelector('.step-status').textContent = 'Done';
                    }

                    // Set current step to active
                    const current = document.getElementById(steps[currentStep]);
                    current.classList.add('active');
                    current.querySelector('.step-status').textContent = 'Running';
                    
                    // Move progress bar
                    mainProgressBar.style.width = ((currentStep + 1) * 24) + '%';
                    currentStep++;

                    // If we haven't hit the last step, wait 2 seconds and move to next
                    if (currentStep < steps.length && modal.classList.contains('active')) {
                        setTimeout(advanceStep, 2000); 
                    } else if (modal.classList.contains('active')) {
                        // We reached the final step. Wait here in a loop until the AI finishes.
                        const waitForAI = setInterval(() => {
                            if (aiCompleted) {
                                clearInterval(waitForAI);
                                mainProgressBar.style.width = '100%';
                                window.location.href = targetUrl;
                            } else if (!modal.classList.contains('active')) {
                                clearInterval(waitForAI); // Stop if modal was closed due to error
                            }
                        }, 500);
                    }
                }
            }

            // Start the visual sequence
            advanceStep();
        });
    }

 


    // --- Accordion Toggle Logic (Results Page) ---
    const toggleButtons = document.querySelectorAll('.toggle-evidence-btn');
    
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Find the parent claim item
            const claimItem = this.closest('.claim-item');
            // Find the body within this item
            const claimBody = claimItem.querySelector('.claim-body');
            
            // Toggle visibility and update button text
            if (claimBody.style.display === 'none') {
                claimBody.style.display = 'block';
                this.innerHTML = '▲ Hide evidence';
            } else {
                claimBody.style.display = 'none';
                this.innerHTML = '▼ Show evidence';
            }
        });
    });

    // --- Copy Link functionality (Results Page) ---
    const copyLinkBtn = document.getElementById('copy-link-btn');
    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', function() {
            // Get the current page URL
            const currentUrl = window.location.href;
            
            // Use the Clipboard API to copy it
            navigator.clipboard.writeText(currentUrl).then(() => {
                // Temporarily change the text to show success
                const originalText = this.innerHTML;
                this.innerHTML = "Copied ✓";
                this.style.color = "var(--accent-green)"; // Turn it green
                
                // Change it back after 2 seconds
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.style.color = "var(--accent-cyan)";
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy text: ', err);
            });
        });
    }

    // --- Verdict Card Modal Logic ---
    const shareResultBtn = document.getElementById('share-result-btn');
    const verdictModal = document.getElementById('verdict-modal');
    const closeVerdictBtn = document.getElementById('close-verdict-btn');

    // Only run if we are on the results page and the elements exist
    if (shareResultBtn && verdictModal && closeVerdictBtn) {
        
        // Open modal
        shareResultBtn.addEventListener('click', function() {
            verdictModal.classList.add('active');
            // Prevent body scrolling while modal is open
            document.body.style.overflow = 'hidden';
        });

        // Close modal via button
        closeVerdictBtn.addEventListener('click', function() {
            verdictModal.classList.remove('active');
            document.body.style.overflow = '';
        });

        // Close modal when clicking outside the card
        verdictModal.addEventListener('click', function(e) {
            if (e.target === verdictModal) {
                verdictModal.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
});