# FactSheild
CityU Fusion X hackathon Project
What is FactShield?
FactShield is an AI-powered misinformation detection platform built for everyday people — not just journalists or researchers.
In a world where fake news spreads 6× faster than real news, most fact-checking tools are too complex, too slow, or built for professionals. FactShield changes that.
Paste any article, URL, or video link. Get a verdict in seconds. Verified forever on the blockchain.

The Problem

Over 1.2 million pieces of misinformation are shared online every day
Existing tools require technical knowledge or journalistic training to use effectively
No single tool handles text, URLs, and video in one place
Fact-check results can be altered, deleted, or disputed — there's no permanent record of truth


Our Solution
FactShield combines three layers of technology into one consumer-friendly platform:
LayerWhat it does AI (NLP)Reads content, isolates individual claims, cross-references trusted sources, and returns a plain-language verdict Blockchain (Polygon)Permanently seals every verification result on-chain — tamper-proof and publicly verifiable Real-time Intelligence Tracks misinformation trends globally via an interactive heatmap updated in real time

Key Features
1. Universal Input
Submit content in any format:

Paste text — copy-paste any article or paragraph
Article URL — paste a link and we fetch + analyze the full content
Video link — paste a YouTube or social media link; we transcribe the audio via Whisper API and fact-check the spoken claims

2. AI Verdict
Every submission gets:

A clear verdict — ✅ REAL, ❌ FAKE, or ⚠️ SUSPICIOUS
A confidence score (e.g. 94.2%)
A plain-language summary — no jargon, no ambiguity
Links to trusted sources (CNN, BBC, NYT, Al Jazeera, WHO) that support or debunk the claim

3. Claim Breakdown
Long articles are broken into individual claims, each verdicted separately:

TRUE — supported by multiple credible sources
FALSE — directly contradicted by credible sources
UNVERIFIED — insufficient evidence found

4. Blockchain Record
Every result is permanently stored on the Polygon network:

Unique transaction hash per verification
Publicly viewable on Polygonscan
Stored on IPFS for decentralized, censorship-resistant access
QR code on every shareable verdict card links back to the full on-chain record

5. Misinformation Heatmap
An interactive world map showing:

Active misinformation hotspots by region
Intensity levels — Critical 🔴, High 🟠, Moderate 🟡
Filter by topic (Health, Politics, War, Finance, Science) and time range

6. act Check Journal
A searchable, filterable archive of all verified content — publicly accessible and updated in real time.
7. Share Your Verdict
Generate a shareable verdict card with:

The verdict badge, confidence score, and summary
Blockchain verification tag
QR code linking to the full report
One-click share to X (Twitter), WhatsApp, or download as image

Pages & User Flow
Splash Page
    └──> Home (Universal Input)
              └──> Analyzing (Loading State)
                        └──> Results Page (Text/URL)
                        └──> Results Page (Video) — includes Deepfake Score
                                  └──> Share Modal
Navbar
    ├──> Heatmap
    ├──> Journal
    └──> About

   

⚠️ Full code coming soon — this repo will be updated with the complete implementation after the primary hackathon submission deadline.
