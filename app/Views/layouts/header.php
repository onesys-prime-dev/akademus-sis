<div class="header">
    <!-- Brand -->
    <span class="header__page-title"><?= esc($pageTitle) ?></span>

    <div class="header__right">

        <div class="header__search">
            <input class="header__search-input" type="text" placeholder="Search">
            <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
                <circle cx="6.5" cy="6.5" r="4.5" stroke-width="1.8"/>
                <path d="M10.5 10.5L14 14" stroke-linecap="round" stroke-width="1.8"/>
            </svg>
        </div>        

        <div class="header__icon">
            <img
                class="header__icon-image"
                src="<?= base_url('assets/images/icons/notification-bell.png') ?>"
                alt="">
        </div>


        <!-- User Menu -->
        <div class="header__user">
            <div class="header__user-avatar">
                <img
                    class="header__user-avatar-image"
                    src="<?= base_url('assets/images/profiles/dingdong-dantes.jpg') ?>"
                    alt="Admin Avatar">
            </div>

            <?php 
                $user = auth()->user();
                $username = ucwords($user->username);
            ?>
            
            <div class="header__user-details">
                <span class="header__user-name">
                    <?= esc($username) ?>
                </span>

                <span class="header__user-role">
                    <?= esc($username) ?>
                </span>
            </div>
        </div>

    </div>
</div>