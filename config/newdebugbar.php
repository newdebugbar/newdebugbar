<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Package State
    |--------------------------------------------------------------------------
    |
    | The "enabled" value controls all profiling, routes, interface work, and
    | the local MCP server. It defaults to true so installing the development
    | package is enough to use it. Set the environment variable to false when
    | a local task needs the package installed but completely inactive.
    |
    */

    'enabled' => env('NEWDEBUGBAR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Allowed Environments
    |--------------------------------------------------------------------------
    |
    | This list decides which Laravel environments may start New Debug Bar.
    | It defaults to "local" because this is a local development tool. Change
    | the list only when an application uses another name for local work.
    |
    */

    'environments' => ['local'],

    // Optional HTTP access check: an invokable class or callable receiving the request.
    // Null keeps the full local experience available. This does not restrict local MCP.
    'access' => null,

    // Request paths to skip, such as 'billing/*'. The default captures every app path.
    'except' => [],

    // Additional sensitive dotted paths; * matches any characters. Built-in rules remain.
    'redact' => [],

    /*
    |--------------------------------------------------------------------------
    | Default Theme
    |--------------------------------------------------------------------------
    |
    | The theme is used until a browser saves its own choice. "system" is the
    | default so the bar follows the developer's light or dark system setting.
    | The other supported starting values are "light" and "dark".
    |
    */

    'theme' => 'system',

    /*
    |--------------------------------------------------------------------------
    | Slow Work Thresholds
    |--------------------------------------------------------------------------
    |
    | "slow_query_ms" marks a query as slow at 100 milliseconds. This catches
    | noticeable database work without warning about ordinary local queries.
    | "slow_http_request_ms" marks an outbound HTTP request as slow at 250
    | milliseconds so remote work that can hold up the page is easy to find.
    | "slow_request_ms" creates a finding for the full request at 1,000
    | milliseconds. These stay configurable because local timings differ by app.
    |
    */

    'slow_query_ms' => 100,

    'slow_http_request_ms' => 250,

    'slow_request_ms' => 1_000,

    /*
    |--------------------------------------------------------------------------
    | Mail Preview Size
    |--------------------------------------------------------------------------
    |
    | Mail previews are captured automatically. "max_body_bytes" limits each
    | retained HTML and text body to 50,000 bytes. "max_attachment_bytes" keeps
    | up to 2 MB of attachment bodies per message so normal files can be opened
    | without letting one message dominate a profile. Metadata remains visible
    | for files beyond that budget. Applications send different mail, so both
    | limits remain configurable.
    | "capture_eml" and "capture_attachment_bodies" default to true for useful
    | local previews. Set either to false when that content should not be saved.
    |
    */

    'mail_preview' => [
        'capture_eml' => true,
        'capture_attachment_bodies' => true,
        'max_body_bytes' => 50_000,
        'max_attachment_bytes' => 2_000_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Finding Rules
    |--------------------------------------------------------------------------
    |
    | Cache miss warnings start after "minimum_cache_operations" reads, which
    | defaults to 5 so one small lookup does not create noise. A warning needs
    | the "high_cache_miss_rate" of 0.8, or 80 percent. "max_findings" defaults
    | to 50 so one profile stays readable even when many rules match. These
    | values stay configurable because expected cache traffic differs by app.
    |
    */

    'findings' => [
        'minimum_cache_operations' => 5,
        'high_cache_miss_rate' => 0.8,
        'max_findings' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Profile Storage
    |--------------------------------------------------------------------------
    |
    | "path" chooses the private profile directory. Null uses Laravel's
    | storage/framework/newdebugbar directory and keeps runtime files out of
    | Git, so a normal app needs no setup.
    | "max_profiles" keeps the latest 20 profiles, enough for a short workflow.
    | "max_age_minutes" also removes profiles after 60 minutes so old local
    | sessions do not keep filling disk space. These settings support longer
    | workflows and custom private storage without changing package code.
    |
    */

    'storage' => [
        'path' => null,
        'max_profiles' => 20,
        'max_age_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Response Limits
    |--------------------------------------------------------------------------
    |
    | "max_items" limits one MCP page to 50 items so agents can inspect large
    | sections in focused parts. "max_bytes" caps one response at 100,000 bytes
    | so a useful result fits in model context without returning a full profile.
    | Both stay configurable because MCP clients have different context limits.
    |
    */

    'mcp' => [
        'max_items' => 50,
        'max_bytes' => 100_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Collection Paths And Size Limits
    |--------------------------------------------------------------------------
    |
    | "application_path" tells New Debug Bar which files belong to the app.
    | Null uses Laravel's base path, which is correct for a normal project.
    | A custom path supports Laravel code kept below another project root.
    | "max_items_per_collector" keeps up to 500 top-level events in each
    | section. "max_items_per_array" keeps up to 100 nested values, request
    | keys, or mail addresses. These defaults preserve a large local sample
    | while keeping profiles quick to encode, store, and open.
    |
    | "max_depth" stops nested captured data after 5 levels. This covers common
    | Laravel context without walking a deep object graph. "max_string_length"
    | keeps 2,000 characters from ordinary captured strings, which preserves
    | useful messages and SQL while bounding unusually large values. These
    | limits stay configurable because local workloads vary in size and shape.
    |
    */

    'collection' => [
        'application_path' => null,
        'max_items_per_collector' => 500,
        'max_items_per_array' => 100,
        'max_depth' => 5,
        'max_string_length' => 2_000,

        /*
        |--------------------------------------------------------------------------
        | Local Diagnostic Values
        |--------------------------------------------------------------------------
        |
        | "query_bindings" controls stored query values. "full" is the default
        | because exact, bounded bindings make local SQL useful and enable copy
        | and EXPLAIN actions. Use "safe" to mask strings or "none" to omit all
        | bindings when the package is used in an unusual shared environment.
        |
        | "key_policy" controls stored cache keys, Redis keys, and cache tags.
        | "full" is the default because a local debugger needs the real bounded
        | key to explain behavior. Use "hash" when stable matching is enough.
        |
        */

        'query_bindings' => env('NEWDEBUGBAR_QUERY_BINDINGS', 'full'),
        'key_policy' => env('NEWDEBUGBAR_KEY_POLICY', 'full'),

        /*
        |--------------------------------------------------------------------------
        | Application Call Sites
        |--------------------------------------------------------------------------
        |
        | "call_sites" records the application location and a short stack for
        | supported events. It defaults to true because knowing where work came
        | from is a core debugging feature. "call_site_frames" retains 5 useful
        | application frames. "call_site_scan_limit" checks up to 40 raw frames
        | so those application frames can still be found below Laravel internals.
        | Set "call_sites" to false only to isolate stack capture overhead in an
        | unusual local workload; the frame limits tune that same cost and detail.
        |
        */

        'call_sites' => true,
        'call_site_frames' => 5,
        'call_site_scan_limit' => 40,

        /*
        |--------------------------------------------------------------------------
        | Exception Evidence
        |--------------------------------------------------------------------------
        |
        | "exception_application_frames" keeps 12 app frames so the failing path
        | is usually complete. "exception_vendor_frames" keeps 12 framework or
        | package frames for deeper tracing. "exception_source_context_lines"
        | keeps 9 lines, showing the failing line with four lines around it.
        | These limits let unusual apps trade stored detail for profile size.
        |
        */

        'exception_application_frames' => 12,
        'exception_vendor_frames' => 12,
        'exception_source_context_lines' => 9,
    ],
];
