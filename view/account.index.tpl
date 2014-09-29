  <div class="container">
    <header class="container-header">
      <h1>Insights Poster Plugin</h1>
      <h2>Post ThinkUp insights to Twitter</h2>
    </header>

    {if $user_is_admin}
    {include file="_plugin.showhider.tpl"}
    {include file="_usermessage.tpl" field="setup"}

    {/if}

    {if $options_markup}
    <p>
    {$options_markup}
    </p>
    {/if}

</div>
