<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SES Tracking Dashboard</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .hero {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: #fff;
            padding: 100px 0 80px;
        }
        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .hero p.lead {
            font-size: 1.25rem;
            color: rgba(255,255,255,0.8);
            max-width: 640px;
            margin: 0 auto;
        }
        .feature-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.1);
        }
        .feature-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1rem;
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            letter-spacing: -0.3px;
        }
        .github-section {
            background-color: #fff;
            border-top: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
        }
        footer {
            background-color: #1a1a2e;
            color: rgba(255,255,255,0.55);
            font-size: 0.875rem;
        }
        footer a {
            color: rgba(255,255,255,0.75);
        }
        footer a:hover {
            color: #fff;
        }
    </style>
</head>
<body>

    {{-- Navbar --}}
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #1a1a2e;">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="fa-solid fa-envelope-open-text me-2 text-primary"></i>SES Tracking
            </a>
            <div class="ms-auto">
                @auth
                    <a href="{{ url('/') }}" class="btn btn-primary btn-sm px-4">
                        <i class="fa-solid fa-gauge me-1"></i> Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm px-4">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> Log In
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- Hero --}}
    <section class="hero text-center">
        <div class="container">
            <div class="mb-4">
                <i class="fa-solid fa-envelope-open-text" style="font-size: 3.5rem; color: #4e9af1;"></i>
            </div>
            <h1 class="mb-4">SES Tracking Dashboard</h1>
            <p class="lead mb-5">
                A self-hosted analytics and activity tracking dashboard for AWS Simple Email Service.
                Monitor email delivery, bounces, complaints, opens, and clicks across multiple projects and teams.
            </p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                @auth
                    <a href="{{ url('/') }}" class="btn btn-primary btn-lg px-5">
                        <i class="fa-solid fa-gauge me-2"></i>Go to Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-5">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Log In
                    </a>
                @endauth
                <a href="https://github.com/alephcom/sestracking" target="_blank" rel="noopener noreferrer"
                   class="btn btn-outline-light btn-lg px-5">
                    <i class="fa-brands fa-github me-2"></i>View on GitHub
                </a>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="py-6 py-md-7" style="padding-top: 5rem; padding-bottom: 5rem;">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Everything you need to monitor your email pipeline</h2>
                <p class="text-muted">Real-time insights into your AWS SES sending activity, all in one place.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card h-100 p-4 shadow-sm">
                        <div class="feature-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fa-solid fa-folder-open"></i>
                        </div>
                        <h5 class="fw-semibold">Multi-project Management</h5>
                        <p class="text-muted mb-0">Organize email tracking across multiple projects and teams, each with its own unique webhook endpoint and access controls.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card h-100 p-4 shadow-sm">
                        <div class="feature-icon bg-success bg-opacity-10 text-success">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <h5 class="fw-semibold">Delivery Tracking</h5>
                        <p class="text-muted mb-0">Confirm which emails were successfully delivered and when, with full per-recipient visibility for every send.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card h-100 p-4 shadow-sm">
                        <div class="feature-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <h5 class="fw-semibold">Bounce &amp; Complaint Monitoring</h5>
                        <p class="text-muted mb-0">Catch hard and soft bounces and spam complaints the moment they happen, protecting your sender reputation.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card h-100 p-4 shadow-sm">
                        <div class="feature-icon bg-warning bg-opacity-10 text-warning">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <h5 class="fw-semibold">Open &amp; Click Analytics</h5>
                        <p class="text-muted mb-0">Track opens and link clicks per recipient, even for emails sent to multiple recipients at once, with Chart.js-powered dashboards.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card h-100 p-4 shadow-sm">
                        <div class="feature-icon bg-info bg-opacity-10 text-info">
                            <i class="fa-solid fa-file-export"></i>
                        </div>
                        <h5 class="fw-semibold">Data Export</h5>
                        <p class="text-muted mb-0">Export activity logs and reports to CSV or Excel with flexible date and project filters for offline analysis and auditing.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card h-100 p-4 shadow-sm">
                        <div class="feature-icon bg-secondary bg-opacity-10 text-secondary">
                            <i class="fa-solid fa-webhook"></i>
                        </div>
                        <h5 class="fw-semibold">Real-time Webhook Processing</h5>
                        <p class="text-muted mb-0">Receives SNS notifications from AWS SES instantly. Idempotent processing ensures no duplicate events, even under retries.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- GitHub CTA --}}
    <section class="github-section py-5">
        <div class="container text-center">
            <i class="fa-brands fa-github mb-3" style="font-size: 2.5rem; color: #333;"></i>
            <h3 class="fw-bold mb-2">Open Source &amp; Self-hosted</h3>
            <p class="text-muted mb-4" style="max-width: 520px; margin: 0 auto 1.5rem;">
                SES Tracking is MIT-licensed and freely available on GitHub. Deploy it on your own infrastructure and keep your email data private.
            </p>
            <a href="https://github.com/alephcom/sestracking" target="_blank" rel="noopener noreferrer"
               class="btn btn-dark btn-lg px-5">
                <i class="fa-brands fa-github me-2"></i>alephcom/sestracking
            </a>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="py-4">
        <div class="container text-center">
            <p class="mb-1">
                SES Tracking &mdash; MIT License &mdash;
                <a href="https://github.com/alephcom/sestracking" target="_blank" rel="noopener noreferrer">github.com/alephcom/sestracking</a>
            </p>
            <p class="mb-0" style="font-size: 0.8rem;">
                Inspired by <a href="https://github.com/Nikeev/sesdashboard" target="_blank" rel="noopener noreferrer">sesdashboard</a> by Nikeev (MIT)
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
