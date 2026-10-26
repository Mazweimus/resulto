<nav class="sidebar sidebar-offcanvas" id="sidebar">
  <ul class="nav">
    <li class="nav-item nav-profile">
      <a href="{{ url('/') }}" class="nav-link">
        <div class="nav-profile-image d-flex align-items-center justify-content-center bg-gradient-primary rounded-circle text-white shadow-sm" style="width: 42px; height: 42px;">
          <i class="mdi mdi-account fs-4"></i>
        </div>
        <div class="nav-profile-text d-flex flex-column ms-3">
          <span class="font-weight-bold mb-1">{{ auth()->check() ? auth()->user()->name : 'Host / Šipkař' }}</span>
          <span class="text-secondary text-small">{{ auth()->check() ? auth()->user()->email : 'Šipkařský turnaj' }}</span>
        </div>
        <i class="mdi mdi-bullseye-arrow text-success nav-profile-badge"></i>
      </a>
    </li>

    {{-- 1. Dashboard --}}
    <li class="nav-item {{ request()->is('/', 'main') ? 'active' : '' }}">
      <a class="nav-link" href="{{ url('/') }}">
        <span class="menu-title">Dashboard</span>
        <i class="mdi mdi-home menu-icon"></i>
      </a>
    </li>

    {{-- 2. Turnaje --}}
    <li class="nav-item {{ request()->is('tournaments*', 'events*', 'cEvent*') ? 'active' : '' }}">
      <a class="nav-link" data-bs-toggle="collapse" href="#tournaments-menu" aria-expanded="{{ request()->is('tournaments*', 'events*', 'cEvent*') ? 'true' : 'false' }}" aria-controls="tournaments-menu">
        <span class="menu-title">Turnaje</span>
        <i class="menu-arrow"></i>
        <i class="mdi mdi-trophy-variant-outline menu-icon"></i>
      </a>
      <div class="collapse {{ request()->is('tournaments*', 'events*', 'cEvent*') ? 'show' : '' }}" id="tournaments-menu">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item">
            <a class="nav-link {{ request()->is('tournaments') ? 'active' : '' }}" href="{{ route('tournaments.index') }}">
              <i class="mdi mdi-format-list-bulleted me-1"></i> Přehled turnajů
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->is('tournaments/create', 'events/create', 'cEvent') ? 'active' : '' }}" href="{{ route('tournaments.create') }}">
              <i class="mdi mdi-plus-circle-outline me-1"></i> Vytvořit turnaj
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->is('tournaments/groups/create') ? 'active' : '' }}" href="{{ route('tournaments.groups.create') }}">
              <i class="mdi mdi-view-grid-plus me-1"></i> Turnaj se skupinami
            </a>
          </li>
        </ul>
      </div>
    </li>

    {{-- 3. Správa hráčů --}}
    <li class="nav-item {{ request()->is('players*') ? 'active' : '' }}">
      <a class="nav-link" data-bs-toggle="collapse" href="#players-menu" aria-expanded="{{ request()->is('players*') ? 'true' : 'false' }}" aria-controls="players-menu">
        <span class="menu-title">Správa hráčů</span>
        <i class="menu-arrow"></i>
        <i class="mdi mdi-account-group menu-icon"></i>
      </a>
      <div class="collapse {{ request()->is('players*') ? 'show' : '' }}" id="players-menu">
        <ul class="nav flex-column sub-menu">
          <li class="nav-item">
            <a class="nav-link {{ request()->is('players') ? 'active' : '' }}" href="{{ route('players.index') }}">
              <i class="mdi mdi-account-multiple me-1"></i> Seznam hráčů
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link {{ request()->is('players/create') ? 'active' : '' }}" href="{{ route('players.create') }}">
              <i class="mdi mdi-account-plus me-1"></i> Přidat hráče
            </a>
          </li>
        </ul>
      </div>
    </li>

    {{-- 4. Žebříček --}}
    <li class="nav-item {{ request()->is('leaderboard*') ? 'active' : '' }}">
      <a class="nav-link" href="{{ route('leaderboard') }}">
        <span class="menu-title">Žebříček</span>
        <i class="mdi mdi-podium-gold menu-icon"></i>
      </a>
    </li>

    {{-- 5. Přihlášení / Registrace --}}
    <li class="nav-item {{ request()->is('login', 'register') ? 'active' : '' }}">
      <a class="nav-link" data-bs-toggle="collapse" href="#auth-menu" aria-expanded="{{ request()->is('login', 'register') ? 'true' : 'false' }}" aria-controls="auth-menu">
        <span class="menu-title">Přihlášení / Registrace</span>
        <i class="menu-arrow"></i>
        <i class="mdi mdi-lock menu-icon"></i>
      </a>
      <div class="collapse {{ request()->is('login', 'register') ? 'show' : '' }}" id="auth-menu">
        <ul class="nav flex-column sub-menu">
          @auth
            <li class="nav-item">
              <form method="POST" action="{{ route('logout') }}" id="sidebar-logout-form">
                @csrf
                <a class="nav-link" href="javascript:void(0)" onclick="document.getElementById('sidebar-logout-form').submit();">
                  <i class="mdi mdi-logout me-2"></i> Odhlásit ({{ auth()->user()->name }})
                </a>
              </form>
            </li>
          @else
            <li class="nav-item">
              <a class="nav-link {{ request()->is('login') ? 'active' : '' }}" href="{{ route('login') }}">
                <i class="mdi mdi-login me-2"></i> Přihlášení
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ request()->is('register') ? 'active' : '' }}" href="{{ route('register') }}">
                <i class="mdi mdi-account-plus me-2"></i> Registrace
              </a>
            </li>
          @endauth
        </ul>
      </div>
    </li>
  </ul>
</nav>
