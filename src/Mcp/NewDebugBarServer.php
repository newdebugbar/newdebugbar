<?php

namespace NewDebugBar\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use NewDebugBar\Mcp\Tools\GetDebugFindings;
use NewDebugBar\Mcp\Tools\GetDebugProfileData;
use NewDebugBar\Mcp\Tools\GetDebugProfileSection;
use NewDebugBar\Mcp\Tools\InspectDebugQueries;
use NewDebugBar\Mcp\Tools\ListDebugProfiles;

final class NewDebugBarServer extends Server
{
    protected string $name = 'New Debug Bar';

    protected string $version = '1.1.0';

    protected string $instructions = 'Read bounded Laravel debug profiles. Use the exact X-NewDebugBar-Profile ID, inspect findings and a small section first, then use get-debug-profile-data with /sections and returned JSON Pointer paths when deeper evidence is needed. For Models, follow /sections/models/payload/model_groups to reach folded writes, identifiers, changed attributes, sources, timings, related queries, and guidance. For Redis, focused items include bounded key evidence and application call sites; follow /sections/redis/payload/items/{index}/callsite for the exact file and line. For Exceptions, focused items summarize retained causes; follow /sections/exceptions/payload/items/{index}/causes for full retained cause evidence. Check /storage for the total-profile byte limit and omitted evidence counts and paths. Section retained_count and transaction_retained_count reflect stored records; capture totals and dropped_count remain separate from storage_omitted_items. For Mail, follow /sections/mail/payload/items/{index}/preview for retained content and omission reasons; null eml or attachment body_base64 means unavailable. Every retained value is requestable: focused-tool omissions are not redaction, and arbitrary text or file content is not guaranteed secret-free.';

    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        ListDebugProfiles::class,
        GetDebugProfileSection::class,
        GetDebugProfileData::class,
        InspectDebugQueries::class,
        GetDebugFindings::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
