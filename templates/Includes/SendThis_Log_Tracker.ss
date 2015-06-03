<% if $Values %>
<dl class="send-this--tracker">
    <% loop $Values %>
        <dt class="send-this--tracker-item--label"><strong><% if $Title %>$Title<% else %>$Name<% end_if %></strong></dt>
        <dd class="send-this--tracker-item--value">$FormattedValue</dd>
    <% end_loop %>
</dl>
<% end_if %>