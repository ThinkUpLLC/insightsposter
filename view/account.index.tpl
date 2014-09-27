<div class="plugin-info">

    <span class="pull-right">{insert name="help_link" id='insightsposter'}</span>
    <h1>
        <img src="{$site_root_path}plugins/insightsposter/assets/img/plugin_icon.png" class="plugin-image">
        Insights Reposter Plugin
    </h1>

    <p>This plugin posts new ThinkUp insights to Twitter.</p>

</div>

{if $user_is_admin}

    {include file="_usermessage.tpl" field="setup"}

    {$options_markup}

{/if}

