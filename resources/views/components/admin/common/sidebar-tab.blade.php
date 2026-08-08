<li class='nav-item {{ ( request()->is("admin/$slug", "admin/$slug/*") ) ? "active" : "" }}'>
    <a href='{{ Route::has("admin::$slug.index") ? route("admin::$slug.index") : "#" }}' class="nav-link">
        <i class="nav-icon fas fa-list"></i>
        <p>{{ $label }}</p>
    </a>
</li>