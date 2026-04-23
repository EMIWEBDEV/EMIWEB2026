<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\DateTime;
use Illuminate\Support\Facades\DB;
use carbon\Carbon;

class TrackingController extends Controller
{
    private const DEFAULT_VIEW_MODE = 'active';
    private const FLOW_FILTER_FULL_PROCESS = 'full_process';
    private const FLOW_FILTER_LIVE_RUNNING = 'live_running';
    private const RUNNING_SCOPE_RECORD = 'record';
    private const RUNNING_SCOPE_LANE = 'lane';
    private const DEFAULT_REFRESH_INTERVAL_SECONDS = 5;
    private const LANE_STATUS_WAIT = 'wait';
    private const LANE_STATUS_PENDING = 'pending';
    private const LANE_STATUS_RUNNING = 'run';
    private const LANE_STATUS_DONE = 'done';
    private const LANE_PROGRESS_NOT_STARTED = 'not_started';
    private const LANE_PROGRESS_PENDING = 'pending';
    private const LANE_PROGRESS_IN_PROGRESS = 'in_progress';
    private const LANE_PROGRESS_COMPLETED = 'completed';

    private const RECORD_CLOSE_POLICY_FINAL_STAGE_ONLY = 'final_stage_only';
    private const RECORD_CLOSE_POLICY_ALL_APPLICABLE_STAGES = 'all_applicable_stages';
    private const DEFAULT_RECORD_CLOSE_POLICY = self::RECORD_CLOSE_POLICY_ALL_APPLICABLE_STAGES;
    private const MERGE_POLICY_ALL_DEPENDENCIES = 'all_dependencies';
    private const RECORD_ORDERING_BY_FLOW_FILTER = [
        self::FLOW_FILTER_FULL_PROCESS => [
            ['field' => 'last_activity_timestamp', 'direction' => 'desc'],
            ['field' => 'is_closed', 'direction' => 'asc'],
            ['field' => 'id', 'direction' => 'asc'],
            ['field' => 'batch', 'direction' => 'asc'],
        ],
        self::FLOW_FILTER_LIVE_RUNNING => [
            ['field' => 'active_lane_status_rank', 'direction' => 'asc'],
            ['field' => 'active_lane_activity_timestamp', 'direction' => 'desc'],
            ['field' => 'active_stage_index', 'direction' => 'asc'],
            ['field' => 'id', 'direction' => 'asc'],
            ['field' => 'batch', 'direction' => 'asc'],
        ],
    ];

    private const SOURCE_DEFINITIONS = [
        'rfid' => [
            // 'table_by_view' => [
            //     'active' => 'N_EMI_Pairing_RFID',
            //     'history' => 'N_EMI_Pairing_RFID_Log',
            // ],
            // 'alias' => 'r',
            'table_union' => [
                'N_EMI_Pairing_RFID',
                'N_EMI_Pairing_RFID_Log',
            ],
            'alias' => 'r',
            'joins' => [
                [
                    'type' => 'left',
                    'table' => 'Emi_Split_Production_Order',
                    'alias' => 'spo',
                    'first' => 'spo.No_Transaksi',
                    'operator' => '=',
                    'second' => 'r.No_Split_Production_Order',
                ],
                [
                    'type' => 'left',
                    'table' => 'EMI_Order_Produksi',
                    'alias' => 'op',
                    'first' => 'op.No_Faktur',
                    'operator' => '=',
                    'second' => 'spo.No_PO',
                ],
                [
                    'type' => 'left',
                    'table' => 'EMI_Master_Routing',
                    'alias' => 'mr',
                    'first' => 'mr.Id_Routing',
                    'operator' => '=',
                    'second' => 'op.Id_Routing',
                ],
            ],
            'where' => [
                ['spo.Status', '=', null],
                ['op.Status', '=', null],
            ],
            'select' => [
                'r.No_Split_Production_Order as prd',
                'op.No_Faktur as prd_order',
                'spo.No_PO as no_po',
                'op.Id_Routing as routing_id',
                'mr.Keterangan as routing_line',
                'r.RFID_Tag as rfid_tag',
                'r.Lokasi_Pairing as lokasi_pairing',
                'r.Tanggal_Pairing as tanggal_pairing',
                'r.Jam_Pairing as jam_pairing',
                'r.Lokasi_IN as lokasi_in',
                'r.Tanggal_IN as tanggal_in',
                'r.Jam_IN as jam_in',
                'r.batch as batch',
            ],
            'map' => [
                'prd' => 'prd',
                'prd_order' => 'prd_order',
                'no_po' => 'no_po',
                'routing_id' => 'routing_id',
                'routing_line' => 'routing_line',
                'rfid_tag' => 'rfid_tag',
                'lokasi_pairing' => 'lokasi_pairing',
                'lokasi_in' => 'lokasi_in',
                'batch' => 'batch',
            ],
            'transforms' => [
                'prd' => 'trim',
                'prd_order' => 'trim',
                'no_po' => 'trim',
                'routing_id' => 'trim',
                'routing_line' => 'trim',
                'rfid_tag' => 'trim',
                'lokasi_pairing' => 'normalize_location',
                'lokasi_in' => 'normalize_location',
                'batch' => 'trim',
            ],
            'computed' => [
                'event_token' => [
                    'type' => 'event_token_from_field',
                    'field' => 'rfid_tag',
                    'fallback_prefix' => 'rfid_row_',
                ],
                'source_key' => ['type' => 'constant', 'value' => 'rfid'],
                'pairing_at' => [
                    'type' => 'combine_datetime',
                    'date_field' => 'tanggal_pairing',
                    'time_field' => 'jam_pairing',
                ],
                'in_at' => ['type' => 'combine_datetime', 'date_field' => 'tanggal_in', 'time_field' => 'jam_in'],
                'source_at' => ['type' => 'constant', 'value' => null],
                'hpp_jumlah_terpakai' => ['type' => 'constant', 'value' => null],
                'flag_hasil_produksi_gr' => ['type' => 'constant', 'value' => null],
                'is_history' => ['type' => 'is_history_by_view_mode'],
                'sequence_at' => ['type' => 'first_non_null', 'fields' => ['in_at', 'pairing_at']],
            ],
        ],
        'production_autoclave' => [
            'table' => 'EMI_Production_Results',
            'alias' => 'pr',
            'joins' => [
                [
                    'type' => 'inner',
                    'table' => 'EMI_VW_Production_Results_HPP',
                    'alias' => 'hpp',
                    'first' => 'hpp.No_Transaksi',
                    'operator' => '=',
                    'second' => 'pr.No_Transaksi',
                ],
                [
                    'type' => 'left',
                    'table' => 'Emi_Split_Production_Order',
                    'alias' => 'spo',
                    'conditions' => [
                        [
                            'first' => 'spo.No_Transaksi',
                            'operator' => '=',
                            'second' => 'pr.No_Production_Order',
                        ],
                        // [
                        //     'first' => 'hpp.Proses',
                        //     'operator' => '=',
                        //     'second' => 'spo.No_Batch',
                        // ],
                    ]
                ],
                [
                    'type' => 'left',
                    'table' => 'EMI_Order_Produksi',
                    'alias' => 'op',
                    'first' => 'op.No_Faktur',
                    'operator' => '=',
                    'second' => 'spo.No_PO',
                ],
                [
                    'type' => 'left',
                    'table' => 'EMI_Master_Routing',
                    'alias' => 'mr',
                    'first' => 'mr.Id_Routing',
                    'operator' => '=',
                    'second' => 'op.Id_Routing',
                ],
            ],
            'select' => [
                'pr.No_Production_Order as prd',
                'op.No_Faktur as prd_order',
                'spo.No_PO as no_po',
                'op.Id_Routing as routing_id',
                'mr.Keterangan as routing_line',
                'hpp.Proses as batch',
                'hpp.Tanggal as hpp_tanggal',
                'hpp.Jam as hpp_jam',
                'hpp.Jumlah_Terpakai as hpp_jumlah_terpakai',
                'hpp.Jumlah_Dosing_Pcs as hpp_jumlah_dosing_pcs',
                'spo.Flag_Hasil_Produksi_GR as flag_hasil_produksi_gr',
                'spo.Tgl_Hasil_Produksi_GR as tgl_hasil_produksi_gr',
                'spo.Jam_Hasil_Produksi_GR as jam_hasil_produksi_gr',
            ],
            'where' => [
                ['pr.Status', '=', null],
                ['spo.Status', '=', null],
                ['op.Status', '=', null],
            ],
            'map' => [
                'prd' => 'prd',
                'prd_order' => 'prd_order',
                'no_po' => 'no_po',
                'routing_id' => 'routing_id',
                'routing_line' => 'routing_line',
                'batch' => 'batch',
                'hpp_tanggal' => 'hpp_tanggal',
                'hpp_jam' => 'hpp_jam',
                'hpp_jumlah_terpakai' => 'hpp_jumlah_terpakai',
                'hpp_jumlah_dosing_pcs' => 'hpp_jumlah_dosing_pcs',
                'flag_hasil_produksi_gr' => 'flag_hasil_produksi_gr',
                'tgl_hasil_produksi_gr' => 'tgl_hasil_produksi_gr',
                'jam_hasil_produksi_gr' => 'jam_hasil_produksi_gr',
            ],
            'transforms' => [
                'prd' => 'trim',
                'prd_order' => 'trim',
                'no_po' => 'trim',
                'routing_id' => 'trim',
                'routing_line' => 'trim',
                'batch' => 'trim',
            ],
            'computed' => [
                'rfid_tag' => ['type' => 'constant', 'value' => ''],
                'event_token' => ['type' => 'index_token', 'prefix' => 'autoclave_hpp_row_'],
                'source_key' => ['type' => 'constant', 'value' => 'production_autoclave'],
                'lokasi_pairing' => ['type' => 'constant', 'value' => ''],
                'pairing_at' => ['type' => 'constant', 'value' => null],
                'lokasi_in' => ['type' => 'constant', 'value' => ''],
                'in_at' => ['type' => 'constant', 'value' => null],
                'source_at' => [
                    'type' => 'combine_datetime',
                    'date_field' => 'hpp_tanggal',
                    'time_field' => 'hpp_jam',
                ],
                'hasil_produksi_gr_at' => [
                    'type' => 'combine_datetime',
                    'date_field' => 'tgl_hasil_produksi_gr',
                    'time_field' => 'jam_hasil_produksi_gr',
                ],
                'is_history' => ['type' => 'constant', 'value' => false],
                'sequence_at' => ['type' => 'first_non_null', 'fields' => ['hasil_produksi_gr_at', 'source_at']],
            ],
        ],
    ];

    private const STAGES = [
        'cold_storage' => [
            'enabled' => true,
            'label' => 'Cold Storage',
            'icon' => '❄️',
            'indicator' => '#38bdf8',
            'unitLabel' => 'box',
            'summaryLabel' => 'Box',
            'source_keys' => ['rfid'],
            // 'temperature' => '-',
            'entry_rules' => [
                [
                    'field' => 'lokasi_pairing',
                    'operator' => 'equals',
                    'value' => 'COLD_STORAGE',
                    'timestamp' => 'pairing_at',
                ],
            ],
            'completion_rules' => [
                [
                    'field' => 'lokasi_in',
                    'operator' => 'equals',
                    'value' => 'GRINDER_IN',
                    'timestamp' => 'in_at',
                ],
            ],
        ],
        'premix' => [
            'enabled' => true,
            'label' => 'Premix',
            'icon' => '🥫',
            'indicator' => '#fa8bef',
            'unitLabel' => 'box',
            'summaryLabel' => 'Box',
            'source_keys' => ['rfid'],
            // 'temperature' => '-',
            'entry_rules' => [
                [
                    'field' => 'lokasi_pairing',
                    'operator' => 'equals',
                    'value' => 'PREMIX',
                    'timestamp' => 'pairing_at',
                ],
            ],
            'completion_rules' => [
                [
                    'field' => 'lokasi_in',
                    'operator' => 'in',
                    'value' => ['PREMIX_MIXER_POUCH_IN', 'PREMIX_MIXER_CAN_IN'],
                    'timestamp' => 'in_at',
                ],
            ],
        ],
        'grinder' => [
            'enabled' => true,
            'label' => 'Grinder',
            'icon' => '⚙️',
            'indicator' => '#fb923c',
            'unitLabel' => 'box',
            'summaryLabel' => 'Box',
            'source_keys' => ['rfid'],
            'quantity' => [
                'mode' => 'matched_rules',
                'rules' => [
                    [
                        'field' => 'lokasi_in',
                        'operator' => 'equals',
                        'value' => 'GRINDER_IN',
                    ],
                ],
            ],
            'completion_policy' => [
                'conditions' => [
                    [
                        'type' => 'compare_stage_quantity',
                        'stage' => 'cold_storage',
                        'operator' => 'gte',
                    ],
                    [
                        'type' => 'dependent_entry_exists',
                        'stages' => ['mixer'],
                        'match' => 'any',
                    ],
                ],
            ],
            // 'temperature' => '-',
            'entry_rules' => [
                [
                    'field' => 'lokasi_in',
                    'operator' => 'equals',
                    'value' => 'GRINDER_IN',
                    'timestamp' => 'in_at',
                ]
                // [
                //     'field' => 'lokasi_pairing',
                //     'operator' => 'equals',
                //     'value' => 'GRINDER_OUT',
                //     'timestamp' => 'pairing_at',
                // ],
            ],
            'completion_rules' => [
                [
                    'field' => 'lokasi_in',
                    'operator' => 'in',
                    'value' => ['GRINDER_OUT'],
                    'timestamp' => 'in_at',
                ],
            ],
        ],
        'mixer' => [
            'enabled' => true,
            'label' => 'Mixer',
            'icon' => '🥣',
            'indicator' => '#a78bfa',
            'unitLabel' => 'box',
            'summaryLabel' => 'Box',
            'source_keys' => ['rfid'],
            'completion_by_entry_of' => ['autoclave'],
            'completion_policy' => [
                'mode' => 'dependency_entry_of',
                'stages' => ['autoclave'],
            ],
            // 'temperature' => '-',
            'merge_from' => ['premix', 'grinder'],
            'pending_rules' => [
                [
                    'field' => 'lokasi_pairing',
                    'operator' => 'equals',
                    'value' => 'GRINDER_OUT',
                    'timestamp' => 'pairing_at',
                ],
            ],
            'entry_rules' => [
                [
                    'field' => 'lokasi_in',
                    'operator' => 'in',
                    'value' => ['MIXER_POUCH_IN', 'MIXER_CAN_IN', 'PREMIX_MIXER_POUCH_IN', 'PREMIX_MIXER_CAN_IN'],
                    'timestamp' => 'in_at',
                ],
            ],
        ],
        // 'effytceh' => [
        //     'enabled' => true,
        //     'label' => 'Effytceh',
        //     'icon' => '🧪',
        //     'indicator' => '#34d399',
        //     'unitLabel' => 'box',
        //     'summaryLabel' => 'Box',
        //     // 'temperature' => '-',
        //     'entry_rules' => [
        //         [
        //             'field' => 'lokasi_pairing',
        //             'operator' => 'contains',
        //             'value' => 'EFFY',
        //             'timestamp' => 'pairing_at',
        //         ],
        //         [
        //             'field' => 'lokasi_in',
        //             'operator' => 'contains',
        //             'value' => 'EFFY',
        //             'timestamp' => 'in_at',
        //         ],
        //     ],
        //     'completion_rules' => [
        //         [
        //             'field' => 'lokasi_in',
        //             'operator' => 'contains',
        //             'value' => 'AUTO',
        //             'timestamp' => 'in_at',
        //         ],
        //     ],
        // ],
        'autoclave' => [
            'enabled' => true,
            'label' => 'Autoclave',
            'icon' => '🔥',
            'indicator' => '#fbbf24',
            'unitLabel' => 'pcs',
            'summaryLabel' => 'Pcs',
            'source_keys' => ['production_autoclave'],
            'quantity' => [
                'mode' => 'sum',
                'source_key' => 'production_autoclave',
                'field' => 'hpp_jumlah_dosing_pcs',
            ],
            'temperature' => '-',
            'entry_rules' => [
                [
                    'field' => 'hpp_jumlah_terpakai',
                    'operator' => 'not_null',
                    'timestamp' => 'source_at',
                ],
            ],
            'completion_rules' => [
                [
                    'field' => 'flag_hasil_produksi_gr',
                    'operator' => 'equals',
                    'value' => 'Y',
                    'timestamp' => 'hasil_produksi_gr_at',
                ],
            ],
            'time' => [
                'start' => [
                    'rules' => 'entry_rules',
                    'timestamp' => 'source_at',
                    'aggregate' => 'min',
                ],
                'end' => [
                    'rules' => 'completion_rules',
                    'timestamp' => 'hasil_produksi_gr_at',
                    'aggregate' => 'max',
                ],
            ],
        ],
    ];

    // Tambahkan override per routing_id di sini saat jalur spesifik sudah dipetakan.
    private const ROUTE_BLUEPRINTS = [
        '*' => [
            'dependencies' => [
                'cold_storage' => [],
                'premix' => [],
                'grinder' => ['cold_storage'],
                'mixer' => ['grinder', 'premix'],
                'autoclave' => ['mixer'],
            ],
            'merge_policy' => [
                'mixer' => self::MERGE_POLICY_ALL_DEPENDENCIES,
            ],
        ],
    ];

    public function index()
    {
        return Inertia('vue/Tracking/Tracking')
        ->withViewData([
            'layout' => 'layouts.clear-view-layout'
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $records = $this->records();
        $flowFilter = $this->resolveFlowFilter((string) $request->input('flow_filter', self::FLOW_FILTER_FULL_PROCESS));
        $runningScope = $this->resolveRunningScope(
            (string) $request->input('running_scope', self::RUNNING_SCOPE_RECORD),
        );
        $filtered = $this->filterRecords($records, $request, $flowFilter, $runningScope);
        $page = max(1, (int) $request->input('page', 1));
        // $perPage = max(1, min(10, (int) $request->input('per_page', 10)));
        $perPage = max(1, min(1000, (int) $request->input('per_page', 10)));
        $pagination = $this->paginateRecords($filtered, $page, $perPage);
        $refreshSeconds = max(
            1,
            (int) config('tracking.realtime_refresh_seconds', self::DEFAULT_REFRESH_INTERVAL_SECONDS),
        );

        return response()->json([
            'meta' => [
                'title' => 'Production Tracking',
                'subtitle' => 'Tracking produksi realtime lintas sumber data',
                'dateLabel' => $this->dateLabel(),
                'liveText' => 'LIVE',
                'supervisor' => 'System',
                'shift' => 'Realtime',
                'line' => 'Realtime Feed',
                'refreshIntervalMs' => $refreshSeconds * 1000,
                'lastUpdatedAt' => now()->toIso8601String(),
            ],
            'columns' => $this->columns(),
            'records' => $pagination['records'],
            'options' => $this->options($records, $request),
            'summary' => $this->summary($filtered),
            'pagination' => $pagination['meta'],
            'filters' => [
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
                'prd' => $request->input('prd', ''),
                'no_split' => $request->input('no_split', ''),
                'batch' => $request->input('batch', ''),
                'line' => $request->input('line', ''),
                'status' => $request->input('status', 'all'),
                'flow_filter' => $flowFilter,
                'running_scope' => $runningScope,
                'page' => $pagination['meta']['page'],
                'per_page' => $pagination['meta']['perPage'],
            ],
        ]);

        // dd($records);
    }

    private function resolveFlowFilter(string $flowFilter): string
    {
        return in_array($flowFilter, [self::FLOW_FILTER_FULL_PROCESS, self::FLOW_FILTER_LIVE_RUNNING], true)
            ? $flowFilter
            : self::FLOW_FILTER_FULL_PROCESS;
    }

    private function resolveRunningScope(string $runningScope): string
    {
        return in_array($runningScope, [self::RUNNING_SCOPE_RECORD, self::RUNNING_SCOPE_LANE], true)
            ? $runningScope
            : self::RUNNING_SCOPE_RECORD;
    }

    private function columns(): array
    {
        return collect($this->stageDefinitions())
            ->map(function (array $stage, string $key) {
                $column = [
                    'key' => $key,
                    'label' => $stage['label'] ?? $key,
                ];

                foreach (['icon', 'indicator', 'unitLabel', 'summaryLabel'] as $attribute) {
                    if (
                        array_key_exists($attribute, $stage) &&
                        $stage[$attribute] !== null &&
                        $stage[$attribute] !== ''
                    ) {
                        $column[$attribute] = $stage[$attribute];
                    }
                }

                return $column;
            })
            ->values()
            ->all();
    }

    private function stageDefinitions(): array
    {
        return array_filter(self::STAGES, function (array $stage) {
            return ($stage['enabled'] ?? true) === true;
        });
    }

    private function stageKeys(): array
    {
        return array_keys($this->stageDefinitions());
    }

    private function stageKeysReversed(): array
    {
        return array_reverse($this->stageKeys());
    }

    private function stageDefinition(string $stage): ?array
    {
        return $this->stageDefinitions()[$stage] ?? null;
    }

    private function stageRules(string $stage, string $ruleType): array
    {
        $definition = $this->stageDefinition($stage);

        if (!$definition) {
            return [];
        }

        return $definition[$ruleType] ?? [];
    }

    private function stageHasCompletionRules(string $stage): bool
    {
        return !empty($this->stageRules($stage, 'completion_rules'));
    }

    private function stageSourceKeys(string $stage): array
    {
        $definition = $this->stageDefinition($stage);

        if (!$definition) {
            return [];
        }

        return array_values(array_filter((array) ($definition['source_keys'] ?? [])));
    }

    private function stageQuantityConfig(string $stage): array
    {
        $definition = $this->stageDefinition($stage);

        if (!$definition) {
            return ['mode' => 'unique_event_token'];
        }

        $quantity = $definition['quantity'] ?? null;

        if (is_array($quantity)) {
            return $quantity + ['mode' => 'unique_event_token'];
        }

        $legacyMode = (string) ($definition['quantity_mode'] ?? '');

        if ($legacyMode === 'hpp_row_count') {
            return [
                'mode' => 'row_count',
                'source_key' => 'production_autoclave',
            ];
        }

        return ['mode' => 'unique_event_token'];
    }

    private function resolveRouteKey(Collection $group): string
    {
        $routeKey = $group
            ->pluck('routing_id')
            ->map(fn($value) => trim((string) $value))
            ->first(fn(string $value) => $value !== '');

        return $routeKey !== null && $routeKey !== '' ? $routeKey : '*';
    }

    private function resolveRouteBlueprint(Collection $group): array
    {
        $routeKey = $this->resolveRouteKey($group);
        $fallbackBlueprint = self::ROUTE_BLUEPRINTS['*'] ?? [];
        $routeBlueprint = self::ROUTE_BLUEPRINTS[$routeKey] ?? [];
        $mergedBlueprint = array_replace_recursive($fallbackBlueprint, $routeBlueprint);
        $dependencies = $this->normalizeBlueprintDependencies((array) ($mergedBlueprint['dependencies'] ?? []));
        $configuredStages = $this->normalizeStageList((array) ($mergedBlueprint['active_stages'] ?? []));
        $observedStages = $this->collectObservedStages($group);
        $activeStages = !empty($configuredStages)
            ? $this->normalizeStageList(array_merge($configuredStages, $observedStages))
            : $this->inferActiveStagesFromObserved($observedStages, $dependencies);

        return [
            'route_key' => $routeKey,
            'active_stages' => $activeStages,
            'dependencies' => $dependencies,
            'merge_policy' => $this->normalizeBlueprintMergePolicies((array) ($mergedBlueprint['merge_policy'] ?? [])),
            'record_completion_policy' => $mergedBlueprint['record_completion_policy'] ?? self::DEFAULT_RECORD_CLOSE_POLICY,
        ];
    }

    private function normalizeStageList(array $stages): array
    {
        $validStages = array_flip($this->stageKeys());

        return array_values(
            array_filter($this->stageKeys(), fn(string $stage) => isset($validStages[$stage]) && in_array($stage, $stages, true)),
        );
    }

    private function normalizeBlueprintDependencies(array $dependencies): array
    {
        return collect($this->stageKeys())
            ->mapWithKeys(function (string $stage) use ($dependencies) {
                $dependencyStages = $this->normalizeStageList((array) ($dependencies[$stage] ?? []));

                return [$stage => $dependencyStages];
            })
            ->all();
    }

    private function normalizeBlueprintMergePolicies(array $mergePolicies): array
    {
        $policies = [];

        foreach ($this->stageKeys() as $stage) {
            $policy = (string) ($mergePolicies[$stage] ?? '');

            if ($policy !== '') {
                $policies[$stage] = $policy;
            }
        }

        return $policies;
    }

    private function collectObservedStages(Collection $group): array
    {
        return collect($this->stageKeys())
            ->filter(function (string $stage) use ($group) {
                return $group->contains(function (array $row) use ($stage) {
                    return $this->rowEnteredStage($row, $stage) ||
                        $this->rowCompletedStage($row, $stage) ||
                        $this->rowPendingStage($row, $stage);
                });
            })
            ->values()
            ->all();
    }

    private function inferActiveStagesFromObserved(array $observedStages, array $dependencies): array
    {
        if (empty($observedStages)) {
            return $this->stageKeys();
        }

        $childrenByStage = [];

        foreach ($dependencies as $stage => $upstreamStages) {
            foreach ($upstreamStages as $upstreamStage) {
                $childrenByStage[$upstreamStage][] = $stage;
            }
        }

        $activeStages = [];
        $queue = array_values($observedStages);

        while (!empty($queue)) {
            $stage = array_shift($queue);

            if (in_array($stage, $activeStages, true)) {
                continue;
            }

            $activeStages[] = $stage;

            foreach ($childrenByStage[$stage] ?? [] as $childStage) {
                if (!in_array($childStage, $activeStages, true)) {
                    $queue[] = $childStage;
                }
            }

            $upstreamStages = $dependencies[$stage] ?? [];

            if (count($upstreamStages) === 1 && !in_array($upstreamStages[0], $activeStages, true)) {
                $queue[] = $upstreamStages[0];
            }
        }

        return $this->normalizeStageList($activeStages);
    }

    private function stageIsApplicable(string $stage, array $blueprint): bool
    {
        return in_array($stage, (array) ($blueprint['active_stages'] ?? []), true);
    }

    private function stageDependencies(string $stage, array $blueprint): array
    {
        return array_values(array_filter((array) (($blueprint['dependencies'] ?? [])[$stage] ?? [])));
    }

    private function stageMergePolicy(string $stage, array $blueprint): string
    {
        return (string) (($blueprint['merge_policy'] ?? [])[$stage] ?? self::MERGE_POLICY_ALL_DEPENDENCIES);
    }

    private function recordCompletionPolicy(array $blueprint): string
    {
        $policy = (string) ($blueprint['record_completion_policy'] ?? self::DEFAULT_RECORD_CLOSE_POLICY);

        return in_array(
            $policy,
            [self::RECORD_CLOSE_POLICY_FINAL_STAGE_ONLY, self::RECORD_CLOSE_POLICY_ALL_APPLICABLE_STAGES],
            true,
        )
            ? $policy
            : self::DEFAULT_RECORD_CLOSE_POLICY;
    }

    private function stageCompletionPolicy(string $stage): array
    {
        $definition = $this->stageDefinition($stage);

        if (!$definition) {
            return [
                'conditions' => [],
                'default' => 'not_completed',
            ];
        }

        $policy = $definition['completion_policy'] ?? null;
        $conditions = [];

        if (is_array($policy) && isset($policy['conditions']) && is_array($policy['conditions'])) {
            foreach ($policy['conditions'] as $condition) {
                if (!is_array($condition)) {
                    continue;
                }

                $normalized = $this->normalizeCompletionCondition($condition);

                if ($normalized !== null) {
                    $conditions[] = $normalized;
                }
            }
        } elseif (is_array($policy)) {
            $legacyCondition = $this->normalizeLegacyCompletionPolicy($policy);

            if ($legacyCondition !== null) {
                $conditions[] = $legacyCondition;
            }
        }

        if (empty($conditions)) {
            $legacyDependentStages = $this->normalizeStageList((array) ($definition['completion_by_entry_of'] ?? []));

            if (!empty($legacyDependentStages)) {
                $conditions[] = [
                    'type' => 'dependent_entry_exists',
                    'stages' => $legacyDependentStages,
                    'match' => 'any',
                ];
            }
        }

        return [
            'conditions' => $conditions,
            'default' => is_array($policy) ? (string) ($policy['default'] ?? 'not_completed') : 'not_completed',
        ];
    }

    private function stagePolicyRules(string $stage, string $ruleKey): array
    {
        $definition = $this->stageDefinition($stage);
        $rawPolicy = $definition['completion_policy'] ?? null;
        $rules = is_array($rawPolicy) ? ($rawPolicy[$ruleKey] ?? null) : null;

        if (is_array($rules) && !empty($rules)) {
            return $rules;
        }

        $policy = $this->stageCompletionPolicy($stage);
        $balanceCondition = collect($policy['conditions'] ?? [])->first(
            fn(array $condition) => (string) ($condition['type'] ?? '') === 'balance',
        );
        $conditionRules = $balanceCondition[$ruleKey] ?? null;

        if (is_array($conditionRules) && !empty($conditionRules)) {
            return $conditionRules;
        }

        return $this->stageRules($stage, $ruleKey);
    }

    private function normalizeLegacyCompletionPolicy(array $policy): ?array
    {
        $mode = (string) ($policy['mode'] ?? '');

        return match ($mode) {
            'balance' => [
                'type' => 'balance',
                'entry_rules' => is_array($policy['entry_rules'] ?? null) ? $policy['entry_rules'] : [],
            ],
            'dependency_entry_of' => [
                'type' => 'dependent_entry_exists',
                'stages' => $this->normalizeStageList((array) ($policy['stages'] ?? [])),
                'match' => 'any',
            ],
            default => null,
        };
    }

    private function normalizeCompletionCondition(array $condition): ?array
    {
        $type = (string) ($condition['type'] ?? '');

        return match ($type) {
            'balance' => [
                'type' => 'balance',
                'entry_rules' => is_array($condition['entry_rules'] ?? null) ? $condition['entry_rules'] : [],
            ],
            'compare_stage_quantity' => $this->normalizeCompareStageQuantityCondition($condition),
            'dependent_entry_exists' => $this->normalizeDependentEntryCondition($condition),
            default => null,
        };
    }

    private function normalizeCompareStageQuantityCondition(array $condition): ?array
    {
        $targetStage = (string) ($condition['stage'] ?? '');

        if ($targetStage === '' || !$this->stageDefinition($targetStage)) {
            return null;
        }

        $operator = strtolower(trim((string) ($condition['operator'] ?? 'gte')));

        if (!in_array($operator, ['gte', 'gt', 'eq', 'lte', 'lt'], true)) {
            $operator = 'gte';
        }

        return [
            'type' => 'compare_stage_quantity',
            'stage' => $targetStage,
            'operator' => $operator,
        ];
    }

    private function normalizeDependentEntryCondition(array $condition): ?array
    {
        $stages = $this->normalizeStageList((array) ($condition['stages'] ?? []));

        if (empty($stages)) {
            return null;
        }

        $match = strtolower(trim((string) ($condition['match'] ?? 'any')));

        if (!in_array($match, ['any', 'all'], true)) {
            $match = 'any';
        }

        return [
            'type' => 'dependent_entry_exists',
            'stages' => $stages,
            'match' => $match,
        ];
    }

    private function stageLabel(string $stage): string
    {
        $definition = $this->stageDefinition($stage);

        return (string) ($definition['label'] ?? str_replace('_', ' ', ucwords($stage, '_')));
    }

    private function humanizeToken(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', ucwords(strtolower(str_replace(['_', '-'], ' ', $value)))));
    }

    private function formatRuleLocationLabel(string $stage, array $rule): string
    {
        $field = (string) ($rule['field'] ?? '');
        $value = $rule['value'] ?? null;
        $stageLabel = $this->stageLabel($stage);

        if ($field === 'lokasi_pairing' || $field === 'lokasi_in') {
            $sourceValue = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;

            if ($sourceValue !== '') {
                if (str_contains($sourceValue, 'OUT')) {
                    return trim($stageLabel . ' Out');
                }

                if (str_contains($sourceValue, 'IN')) {
                    return trim($stageLabel . ' In');
                }

                $sourceLabel = $this->humanizeToken($sourceValue);

                if (strtolower($sourceLabel) === strtolower($stageLabel)) {
                    return $stageLabel;
                }

                return trim($stageLabel . ' ' . $sourceLabel);
            }

            return trim($stageLabel . ' ' . $this->humanizeToken($field));
        }

        if ($field === 'hpp_jumlah_terpakai') {
            return 'HPP Terpakai';
        }

        if ($field === 'flag_hasil_produksi_gr') {
            return 'Hasil Produksi GR';
        }

        return $this->humanizeToken((string) ($rule['label'] ?? $field));
    }

    private function countUniqueEventTokens(Collection $rows): int
    {
        return $rows->pluck('event_token')->filter()->unique()->count();
    }

    private function stageRuleBreakdown(string $stage, Collection $stageRows, string $ruleType): array
    {
        $rules = $this->stageRules($stage, $ruleType);

        if (empty($rules)) {
            return [];
        }

        return collect($rules)
            ->map(function (array $rule, int $index) use ($stage, $stageRows, $ruleType) {
                $matchedRows = $stageRows->filter(fn(array $row) => $this->matchesStageRule($row, $rule))->values();
                $count = $this->countUniqueEventTokens($matchedRows);

                if ($count === 0) {
                    return null;
                }

                // dd($stage, $ruleType, $index, $rule, $matchedRows, $count);
                return [
                    'key' => $stage . ':' . $ruleType . ':' . $index,
                    'label' => $this->formatRuleLocationLabel($stage, $rule),
                    'count' => $count,
                    'unit' => (string) ($this->stageDefinition($stage)['summaryLabel'] ?? 'Box'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function stageRuleBreakdownFromRules(
        string $stage,
        Collection $stageRows,
        array $rules,
        string $ruleType,
    ): array {
        if (empty($rules)) {
            return [];
        }

        return collect($rules)
            ->map(function (array $rule, int $index) use ($stage, $stageRows, $ruleType) {
                $matchedRows = $stageRows->filter(fn(array $row) => $this->matchesStageRule($row, $rule))->values();
                $count = $this->countUniqueEventTokens($matchedRows);

                if ($count === 0) {
                    return null;
                }

                return [
                    'key' => $stage . ':' . $ruleType . ':' . $index,
                    'label' => $this->formatRuleLocationLabel($stage, $rule),
                    'count' => $count,
                    'unit' => (string) ($this->stageDefinition($stage)['summaryLabel'] ?? 'Box'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function countUniqueEventTokensByRules(Collection $rows, array $rules): int
    {
        if ($rows->isEmpty()) {
            return 0;
        }

        if (empty($rules)) {
            return $this->countUniqueEventTokens($rows);
        }

        return $this->countUniqueEventTokens(
            $rows
                ->filter(function (array $row) use ($rules) {
                    return $this->matchesStageRules($row, $rules);
                })
                ->values(),
        );
    }

    private function formatBreakdownSummary(array $items): ?string
    {
        if (empty($items)) {
            return null;
        }

        return collect($items)
            ->map(function (array $item) {
                $count = number_format((int) ($item['count'] ?? 0), 0, ',', '.');
                $label = (string) ($item['label'] ?? '');
                $unit = strtolower((string) ($item['unit'] ?? 'Box'));

                return trim($label . ' ' . $count . ' ' . $unit);
            })
            ->implode(' · ');
    }

    private function buildLaneBreakdown(string $stage, Collection $stageRows): array
    {
        $entryRules = $this->stagePolicyRules($stage, 'entry_rules');
        $completionRules = $this->stagePolicyRules($stage, 'completion_rules');
        $entryBreakdown = $this->stageRuleBreakdownFromRules($stage, $stageRows, $entryRules, 'entry_rules');
        $completionBreakdown = $this->stageRuleBreakdownFromRules(
            $stage,
            $stageRows,
            $completionRules,
            'completion_rules',
        );

        return [
            'entry_breakdown' => $entryBreakdown,
            'completion_breakdown' => $completionBreakdown,
        ];
    }

    // private function records(): array
    // {
    //     $rows = $this->trackingSourceRows();

    //     if ($rows->isEmpty()) {
    //         return [];
    //     }

    //     $dataGrouped = $rows
    //         ->groupBy(fn(array $row) => $this->recordGroupKey($row))
    //         ->map(function (Collection $group) {
    //             $totalBoxes = $group->pluck('event_token')->filter()->unique()->count();
    //             $blueprint = $this->resolveRouteBlueprint($group);
    //             $lanes = $this->buildLaneMetrics($group, $blueprint, $totalBoxes);
    //             $isClosed = $this->isRecordClosed($lanes, $blueprint);
    //             $firstRow = $group->first() ?? [];
    //             $noSplit = trim((string) ($firstRow['prd'] ?? ''));
    //             $prdOrder = trim((string) ($firstRow['prd_order'] ?? ''));
    //             $groupBatch = $this->normalizeGroupBatch($firstRow['batch'] ?? null);
    //             $groupKey = $this->recordCompositeId($noSplit, $groupBatch);

    //             return [
    //                 'id' => $noSplit,
    //                 'group_key' => $groupKey,
    //                 'prd' => $noSplit,
    //                 'prd_order' => $prdOrder !== '' ? $prdOrder : $noSplit,
    //                 'no_po' => trim((string) ($firstRow['no_po'] ?? '')),
    //                 'routing_id' => trim((string) ($firstRow['routing_id'] ?? '')),
    //                 'routing_line' => trim((string) ($firstRow['routing_line'] ?? '')),
    //                 'source' => 'live',
    //                 'batch' => $this->formatBatch($groupBatch),
    //                 'batch_raw' => [$groupBatch],
    //                 'date' => $this->resolveRecordDate($group),
    //                 'full_done' => $isClosed,
    //                 'is_closed' => $isClosed,
    //                 'route_key' => $blueprint['route_key'] ?? '*',
    //                 'applicable_stage_keys' => $blueprint['active_stages'] ?? [],
    //                 'lanes' => $lanes,
    //             ];
    //         })
    //         ->sortBy([['is_closed', 'asc'], ['date', 'desc'], ['id', 'asc'], ['batch', 'asc']])
    //         ->values()
    //         ->all();
    //     // dd($dataGrouped);
    //     return $dataGrouped;
    // }

    private function records(): array
    {
        $rows = $this->trackingSourceRows();

        if ($rows->isEmpty()) {
            return [];
        }

        $dataGrouped = $rows
            ->groupBy(fn(array $row) => $this->recordGroupKey($row))
            ->map(function (Collection $group) {
                $totalBoxes = $group->pluck('event_token')->filter()->unique()->count();
                $blueprint = $this->resolveRouteBlueprint($group);
                $lanes = $this->buildLaneMetrics($group, $blueprint, $totalBoxes);
                $isClosed = $this->isRecordClosed($lanes, $blueprint);
                $firstRow = $group->first() ?? [];
                $noSplit = trim((string) ($firstRow['prd'] ?? ''));
                $prdOrder = trim((string) ($firstRow['prd_order'] ?? ''));
                $groupBatch = $this->normalizeGroupBatch($firstRow['batch'] ?? null);
                $groupKey = $this->recordCompositeId($noSplit, $groupBatch);

                $lastActivityDate = $this->resolveLastActivityDate($group);
                $activeLaneSort = $this->resolveRecordActiveLaneSort($lanes);

                return [
                    'id' => $noSplit,
                    'group_key' => $groupKey,
                    'prd' => $noSplit,
                    'prd_order' => $prdOrder !== '' ? $prdOrder : $noSplit,
                    'no_po' => trim((string) ($firstRow['no_po'] ?? '')),
                    'routing_id' => trim((string) ($firstRow['routing_id'] ?? '')),
                    'routing_line' => trim((string) ($firstRow['routing_line'] ?? '')),
                    'source' => 'live',
                    'batch' => $this->formatBatch($groupBatch),
                    'batch_raw' => [$groupBatch],
                    'date' => $this->resolveRecordDate($group),
                    'full_done' => $isClosed,
                    'is_closed' => $isClosed,
                    'route_key' => $blueprint['route_key'] ?? '*',
                    'applicable_stage_keys' => $blueprint['active_stages'] ?? [],
                    'lanes' => $lanes,
                    'last_activity_timestamp' => $lastActivityDate->timestamp,
                    'active_lane_status_rank' => $activeLaneSort['status_rank'],
                    'active_lane_activity_timestamp' => $activeLaneSort['activity_timestamp'],
                    'active_stage_index' => $activeLaneSort['stage_index'],
                ];
            })
            ->values()
            ->all();

        return $dataGrouped;
    }

    private function filterRecords(array $records, Request $request, string $flowFilter, string $runningScope): array
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $prd = $request->input('prd');
        $noSplit = trim((string) $request->input('no_split', ''));
        $batch = $request->input('batch');
        $line = $request->input('line');
        $status = $request->input('status', 'all');

        $filtered = array_values(
            array_filter($records, function (array $record) use ($from, $to, $prd, $noSplit, $batch, $line, $status) {
                // Hide records that have not entered any stage (no lanes)
                $hasAnyStageData = collect($record['lanes'] ?? [])
                    ->contains(function (array $lane) {
                        return ($lane['qty'] ?? null) !== null ||
                            in_array(
                                (string) ($lane['status'] ?? ''),
                                [self::LANE_STATUS_PENDING, self::LANE_STATUS_RUNNING, self::LANE_STATUS_DONE],
                                true,
                            );
                    });

                if (!$hasAnyStageData) {
                    return false;
                }
                if ($from && $record['date'] < $from) {
                    return false;
                }

                if ($to && $record['date'] > $to) {
                    return false;
                }

                if ($prd && ($record['prd_order'] ?? '') !== $prd) {
                    return false;
                }

                if ($noSplit !== '' && (string) ($record['prd'] ?? '') !== $noSplit) {
                    return false;
                }

                if ($batch && !in_array($batch, $record['batch_raw'] ?? [], true)) {
                    return false;
                }

                if ($line && ($record['routing_line'] ?? '') !== $line) {
                    return false;
                }

                if ($status === 'selesai' && !$record['full_done']) {
                    return false;
                }

                if ($status === 'running') {
                    $hasRunningLane = collect($record['lanes'])->contains(
                        fn(array $lane) => ($lane['status'] ?? '') === self::LANE_STATUS_RUNNING,
                    );

                    if ($record['full_done'] || !$hasRunningLane) {
                        return false;
                    }
                }

                if ($status === 'pending') {
                    $hasPendingLane = collect($record['lanes'])->contains(
                        fn(array $lane) => ($lane['status'] ?? '') === self::LANE_STATUS_PENDING,
                    );

                    if ($record['full_done'] || !$hasPendingLane) {
                        return false;
                    }
                }

                return true;
            }),
        );

        if ($flowFilter === self::FLOW_FILTER_LIVE_RUNNING) {
            $filtered = $this->applyLiveRunningFilter($filtered, $runningScope, $status);
        }

        return $this->sortRecordsForFlowFilter($filtered, $flowFilter);
    }

    private function applyLiveRunningFilter(array $records, string $runningScope, string $status): array
    {
        $visibleStatuses = $this->liveVisibleLaneStatuses($status);

        if ($runningScope === self::RUNNING_SCOPE_LANE) {
            return array_values(
                array_filter(
                    array_map(function (array $record) use ($visibleStatuses) {
                        $runningLanes = array_values(
                            array_filter(
                                $record['lanes'] ?? [],
                                fn(array $lane) => $this->laneIsLiveVisible($lane, $visibleStatuses),
                            ),
                        );

                        if (empty($runningLanes)) {
                            return null;
                        }

                        $record['lanes'] = $this->sortLiveLanes($runningLanes);
                        $activeLaneSort = $this->resolveRecordActiveLaneSort($record['lanes']);
                        $record['active_lane_status_rank'] = $activeLaneSort['status_rank'];
                        $record['active_lane_activity_timestamp'] = $activeLaneSort['activity_timestamp'];
                        $record['active_stage_index'] = $activeLaneSort['stage_index'];

                        return $record;
                    }, $records),
                ),
            );
        }

        return array_values(
            array_filter(
                array_map(function (array $record) use ($status, $visibleStatuses) {
                    $hasVisibleLane = collect($record['lanes'] ?? [])->contains(
                        fn(array $lane) => $this->laneIsLiveVisible($lane, $visibleStatuses),
                    );

                    if (!$hasVisibleLane) {
                        return null;
                    }

                    if (in_array($status, ['running', 'pending'], true)) {
                        $record['lanes'] = $this->sortLiveLanes(
                            array_values(
                                array_filter(
                                    $record['lanes'] ?? [],
                                    fn(array $lane) => $this->laneIsLiveVisible($lane, $visibleStatuses),
                                ),
                            ),
                        );
                    }

                    return $record;
                }, $records),
            ),
        );
    }

    private function liveVisibleLaneStatuses(string $status): array
    {
        return match ($status) {
            'running' => [self::LANE_STATUS_RUNNING],
            'pending' => [self::LANE_STATUS_PENDING],
            default => [self::LANE_STATUS_RUNNING, self::LANE_STATUS_PENDING],
        };
    }

    private function laneIsLiveVisible(array $lane, ?array $visibleStatuses = null): bool
    {
        return in_array(
            (string) ($lane['status'] ?? ''),
            $visibleStatuses ?? [self::LANE_STATUS_RUNNING, self::LANE_STATUS_PENDING],
            true,
        );
    }

    private function sortLiveLanes(array $lanes): array
    {
        return collect($lanes)
            ->sort(function (array $left, array $right) {
                $leftRank = $this->laneStatusRank($left);
                $rightRank = $this->laneStatusRank($right);

                if ($leftRank !== $rightRank) {
                    return $leftRank <=> $rightRank;
                }

                $leftActivity = (int) ($left['activity_timestamp'] ?? 0);
                $rightActivity = (int) ($right['activity_timestamp'] ?? 0);

                if ($leftActivity !== $rightActivity) {
                    return $rightActivity <=> $leftActivity;
                }

                return $this->stageIndex((string) ($left['column_key'] ?? '')) <=>
                    $this->stageIndex((string) ($right['column_key'] ?? ''));
            })
            ->values()
            ->all();
    }

    private function laneStatusRank(array $lane): int
    {
        return match ((string) ($lane['status'] ?? '')) {
            self::LANE_STATUS_RUNNING => 1,
            self::LANE_STATUS_PENDING => 2,
            self::LANE_STATUS_DONE => 3,
            default => 4,
        };
    }

    private function resolveRecordActiveLaneSort(array $lanes): array
    {
        $activeLanes = collect($lanes)
            ->filter(fn(array $lane) => $this->laneIsLiveVisible($lane))
            ->values();

        if ($activeLanes->isEmpty()) {
            $activeLanes = collect($lanes)
                ->filter(fn(array $lane) => (string) ($lane['status'] ?? '') !== self::LANE_STATUS_WAIT)
                ->values();
        }

        if ($activeLanes->isEmpty()) {
            return [
                'status_rank' => 9,
                'activity_timestamp' => 0,
                'stage_index' => 999,
            ];
        }

        $sorted = $this->sortLiveLanes($activeLanes->all());
        $first = $sorted[0] ?? [];

        return [
            'status_rank' => $this->laneStatusRank($first),
            'activity_timestamp' => (int) ($first['activity_timestamp'] ?? 0),
            'stage_index' => $this->stageIndex((string) ($first['column_key'] ?? '')),
        ];
    }

    private function sortRecordsForFlowFilter(array $records, string $flowFilter): array
    {
        $ordering = self::RECORD_ORDERING_BY_FLOW_FILTER[$flowFilter]
            ?? self::RECORD_ORDERING_BY_FLOW_FILTER[self::FLOW_FILTER_FULL_PROCESS];

        return collect($records)
            ->sort(fn(array $left, array $right) => $this->compareRecordsByOrdering($left, $right, $ordering))
            ->values()
            ->all();
    }

    private function compareRecordsByOrdering(array $left, array $right, array $ordering): int
    {
        foreach ($ordering as $rule) {
            $field = (string) ($rule['field'] ?? '');

            if ($field === '') {
                continue;
            }

            $direction = strtolower((string) ($rule['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            $result = $this->compareSortValues($left[$field] ?? null, $right[$field] ?? null);

            if ($result !== 0) {
                return $direction === 'desc' ? -$result : $result;
            }
        }

        return 0;
    }

    private function compareSortValues($left, $right): int
    {
        if ($left === $right) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        if (is_bool($left) || is_bool($right)) {
            return ((int) $left) <=> ((int) $right);
        }

        if (is_numeric($left) && is_numeric($right)) {
            return ((float) $left) <=> ((float) $right);
        }

        return strcmp((string) $left, (string) $right);
    }

    private function stageIndex(string $stage): int
    {
        $index = array_search($stage, $this->stageKeys(), true);

        return $index === false ? 999 : (int) $index;
    }

    private function options(array $records, Request $request): array
    {
        $selected = [
            'line' => trim((string) $request->input('line', '')),
            'prd' => trim((string) $request->input('prd', '')),
            'no_split' => trim((string) $request->input('no_split', '')),
            'batch' => trim((string) $request->input('batch', '')),
        ];

        $recordsForLine = $this->filterOptionRecords($records, $selected, 'line');
        $recordsForPrd = $this->filterOptionRecords($records, $selected, 'prd');
        $recordsForSplit = $this->filterOptionRecords($records, $selected, 'no_split');
        $recordsForBatch = $this->filterOptionRecords($records, $selected, 'batch');

        $splitByPrd = collect($recordsForSplit)
            ->groupBy(fn(array $record) => (string) ($record['prd_order'] ?? ''))
            ->map(function (Collection $group) {
                return $group->pluck('prd')->filter()->unique()->values()->all();
            })
            ->all();
        // dd($splitByPrd);

        return [
            'prd' => collect($recordsForPrd)->pluck('prd_order')->filter()->unique()->values()->all(),
            'no_split' => collect($recordsForSplit)->pluck('prd')->filter()->unique()->values()->all(),
            'no_split_by_prd' => $splitByPrd,
            'batch' => collect($recordsForBatch)
                ->flatMap(fn(array $record) => $record['batch_raw'] ?? [])
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'line' => collect($recordsForLine)->pluck('routing_line')->filter()->unique()->values()->all(),
            'status' => [
                ['label' => 'Semua', 'value' => 'all'],
                ['label' => 'Running', 'value' => 'running'],
                ['label' => 'Menunggu', 'value' => 'pending'],
                ['label' => 'Selesai', 'value' => 'selesai'],
            ],
            'flow_filter_modes' => [
                ['label' => 'Full Proses', 'value' => self::FLOW_FILTER_FULL_PROCESS],
                ['label' => 'Live Running', 'value' => self::FLOW_FILTER_LIVE_RUNNING],
            ],
            'running_scope_modes' => [
                ['label' => 'Per Nomor', 'value' => self::RUNNING_SCOPE_RECORD],
                ['label' => 'Per Mesin', 'value' => self::RUNNING_SCOPE_LANE],
            ],
        ];
    }

    private function filterOptionRecords(array $records, array $selected, string $exclude): array
    {
        return array_values(
            array_filter($records, function (array $record) use ($selected, $exclude) {
                if ($exclude !== 'line' && ($selected['line'] ?? '') !== '') {
                    if ((string) ($record['routing_line'] ?? '') !== (string) $selected['line']) {
                        return false;
                    }
                }

                if ($exclude !== 'prd' && ($selected['prd'] ?? '') !== '') {
                    if ((string) ($record['prd_order'] ?? '') !== (string) $selected['prd']) {
                        return false;
                    }
                }

                if ($exclude !== 'no_split' && ($selected['no_split'] ?? '') !== '') {
                    if ((string) ($record['prd'] ?? '') !== (string) $selected['no_split']) {
                        return false;
                    }
                }

                if ($exclude !== 'batch' && ($selected['batch'] ?? '') !== '') {
                    if (!in_array((string) $selected['batch'], $record['batch_raw'] ?? [], true)) {
                        return false;
                    }
                }

                return true;
            }),
        );
    }

    private function applicableLanes(array $record): Collection
    {
        $applicableStages = array_values(array_filter((array) ($record['applicable_stage_keys'] ?? [])));

        if (empty($applicableStages)) {
            return collect($record['lanes'] ?? []);
        }

        return collect($record['lanes'] ?? [])->filter(
            fn(array $lane) => in_array((string) ($lane['column_key'] ?? ''), $applicableStages, true),
        );
    }

    private function summary(array $records): array
    {
        return [
            'total' => count($records),
            'done' => collect($records)->where('full_done', true)->count(),
            'running' => collect($records)
                ->filter(function (array $record) {
                    return !$record['full_done'] &&
                        $this->applicableLanes($record)->contains(
                            fn(array $lane) => ($lane['status'] ?? '') === self::LANE_STATUS_RUNNING,
                        );
                })
                ->count(),
            'pending' => collect($records)
                ->filter(function (array $record) {
                    return !$record['full_done'] &&
                        $this->applicableLanes($record)->contains(
                            fn(array $lane) => ($lane['status'] ?? '') === self::LANE_STATUS_PENDING,
                        );
                })
                ->count(),
        ];
    }

    private function paginateRecords(array $records, int $page, int $perPage): array
    {
        $total = count($records);
        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min(max($page, 1), $lastPage);
        $offset = ($page - 1) * $perPage;

        return [
            'records' => array_slice($records, $offset, $perPage),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
                'hasMore' => $page < $lastPage,
            ],
        ];
    }

    private function dateLabel(): string
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        $now = now();

        return sprintf('%s, %d %s %s', $days[$now->dayOfWeek], $now->day, $months[$now->month - 1], $now->year);
    }

    private function trackingSourceRows(): Collection
    {
        return collect($this->activeSourceKeys())
            ->flatMap(fn(string $sourceKey) => $this->sourceRows($sourceKey, self::DEFAULT_VIEW_MODE))
            ->filter(fn(array $row) => $row['prd'] !== '')
            ->sortBy([['prd', 'asc'], ['event_token', 'asc'], ['sequence_at', 'asc'], ['is_history', 'asc']])
            ->values();
    }

    private function activeSourceKeys(): array
    {
        return array_values(
            array_unique(
                collect($this->stageDefinitions())
                    ->flatMap(fn(array $stage) => $stage['source_keys'] ?? [])
                    ->filter()
                    ->all(),
            ),
        );
    }

    private function sourceDefinition(string $sourceKey): ?array
    {
        return self::SOURCE_DEFINITIONS[$sourceKey] ?? null;
    }

    private function sourceTableForViewMode(array $definition, string $viewMode): ?string
    {
        $tableByView = $definition['table_by_view'] ?? null;

        if (is_array($tableByView)) {
            return $tableByView[$viewMode] ?? ($tableByView['active'] ?? null);
        }

        return $definition['table'] ?? null;
    }

    private function sourceRows(string $sourceKey, string $viewMode = 'active'): Collection
    {
        $definition = $this->sourceDefinition($sourceKey);

        if (!$definition) {
            return collect();
        }

        $alias = (string) ($definition['alias'] ?? 't');

        try {

            if (!empty($definition['table_union'])) {

                $unionQuery = $this->buildUnionQuery(
                    $definition['table_union'],
                    $alias,
                    $definition
                );

                $rows = DB::query()
                    ->fromSub($unionQuery, 'unioned')
                    ->get();

            } else {

                $table = $this->sourceTableForViewMode($definition, $viewMode);

                if (!$table) {
                    return collect();
                }

                $query = DB::table($table . ' as ' . $alias);
                $this->applySourceJoins($query, $definition['joins'] ?? []);
                if (!empty($definition['where'])) {
                    $query->where($definition['where']);
                }

                // dd(
                //     $query->toSql(),
                //     $query->getBindings()
                // );
                $rows = $query->select($definition['select'] ?? [])->get();
                // dd($sourceKey, $viewMode, $definition, $rows->first());
                // dd($rows->where('prd', 'PR0425-00028-1'));
            } 

            

            return collect($rows)
                ->values()
                ->map(fn($row, int $index) =>
                    $this->normalizeSourceRow($row, $sourceKey, $definition, $index, $viewMode)
                )
                ->values();

        } catch (Throwable $exception) {

            if (config('app.debug')) {
                Log::warning('Tracking source query failed.', [
                    'source_key' => $sourceKey,
                    'alias' => $alias,
                    'view_mode' => $viewMode,
                    'message' => $exception->getMessage(),
                ]);
            }

            return collect();
        }
    }

    // private function applySourceJoins($query, array $joins): void
    // {
    //     foreach ($joins as $join) {
    //         $joinType = (string) ($join['type'] ?? 'left');
    //         $joinTable = (string) ($join['table'] ?? '');
    //         $joinAlias = (string) ($join['alias'] ?? '');
    //         $first = (string) ($join['first'] ?? '');
    //         $operator = (string) ($join['operator'] ?? '=');
    //         $second = (string) ($join['second'] ?? '');

    //         if ($joinTable === '' || $joinAlias === '' || $first === '' || $second === '') {
    //             continue;
    //         }

    //         $tableWithAlias = $joinTable . ' as ' . $joinAlias;

    //         if ($joinType === 'inner') {
    //             $query->join($tableWithAlias, $first, $operator, $second);
    //             continue;
    //         }

    //         if ($joinType === 'left') {
    //                 $query->leftJoin($tableWithAlias, $first, $operator, $second
    //             );
    //             continue;
    //         }

    //         $query->leftJoin($tableWithAlias, $first, $operator, $second);
    //     }
    // }

    private function applySourceJoins($query, array $joins): void
    {
        foreach ($joins as $join) {
            $joinType = (string) ($join['type'] ?? 'left');
            $joinTable = (string) ($join['table'] ?? '');
            $joinAlias = (string) ($join['alias'] ?? '');

            if ($joinTable === '' || $joinAlias === '') {
                continue;
            }

            $tableWithAlias = $joinTable . ' as ' . $joinAlias;
            $conditions = $join['conditions'] ?? null;

            if (is_array($conditions) && count($conditions) > 0) {
                $joinMethod = $joinType === 'inner' ? 'join' : 'leftJoin';

                $query->$joinMethod($tableWithAlias, function ($joinClause) use ($conditions) {
                    foreach ($conditions as $cond) {
                        $first = $cond['first'] ?? null;
                        $operator = $cond['operator'] ?? '=';
                        $second = $cond['second'] ?? null;

                        if ($first && $second) {
                            $joinClause->on($first, $operator, $second);
                        }
                    }
                });

                continue;
            }

            $first = (string) ($join['first'] ?? '');
            $operator = (string) ($join['operator'] ?? '=');
            $second = (string) ($join['second'] ?? '');

            if ($first === '' || $second === '') {
                continue;
            }

            if ($joinType === 'inner') {
                $query->join($tableWithAlias, $first, $operator, $second);
            } else {
                $query->leftJoin($tableWithAlias, $first, $operator, $second);
            }
        }
    }

    private function normalizeSourceRow($row, string $sourceKey, array $definition, int $index, string $viewMode): array
    {
        $raw = (array) $row;
        $map = $definition['map'] ?? [];
        $transforms = $definition['transforms'] ?? [];
        $computed = $definition['computed'] ?? [];
        $normalized = [];

        foreach ($map as $normalizedField => $sourceField) {
            $value = $this->rowValue($raw, (string) $sourceField);

            if (isset($transforms[$normalizedField])) {
                $value = $this->applyTransform((string) $transforms[$normalizedField], $value);
            }

            $normalized[$normalizedField] = $value;
        }

        foreach ($computed as $field => $payload) {
            $normalized[$field] = $this->resolveComputedValue(
                $normalized,
                (string) ($payload['type'] ?? ''),
                $payload,
                $index,
                $viewMode,
                $raw,
                $sourceKey,
            );
        }

        $defaults = [
            'prd' => '',
            'prd_order' => '',
            'no_po' => '',
            'routing_id' => '',
            'routing_line' => '',
            'rfid_tag' => '',
            'event_token' => $sourceKey . '_row_' . $index,
            'source_key' => $sourceKey,
            'lokasi_pairing' => '',
            'pairing_at' => null,
            'lokasi_in' => '',
            'in_at' => null,
            'source_at' => null,
            'hpp_tanggal' => null,
            'hpp_jam' => null,
            'hpp_jumlah_terpakai' => null,
            'flag_hasil_produksi_gr' => null,
            'tgl_hasil_produksi_gr' => null,
            'jam_hasil_produksi_gr' => null,
            'hasil_produksi_gr_at' => null,
            'batch' => '',
            'is_history' => false,
            'sequence_at' => null,
        ];

        return $normalized + $defaults;
    }

    private function rowValue(array $row, string $field)
    {
        return $row[$field] ?? null;
    }

    private function applyTransform(string $transform, $value)
    {
        return match ($transform) {
            'trim' => trim((string) $value),
            'upper_trim' => strtoupper(trim((string) $value)),
            'normalize_location' => $this->normalizeLocation($value === null ? null : (string) $value),
            default => $value,
        };
    }

    private function resolveComputedValue(
        array $normalized,
        string $computedType,
        array $payload,
        int $index,
        string $viewMode,
        array $row,
        string $sourceKey,
    ) {
        // dd($normalized, $computedType, $payload, $index, $viewMode, $row, $sourceKey);
        // if($sourceKey == 'flag_hasil_produksi_gr') {
        //     dd($normalized, $computedType, $payload, $index, $viewMode, $row, $sourceKey);
        // }
        return match ($computedType) {
            'constant' => $payload['value'] ?? null,
            'index_token' => (string) ($payload['prefix'] ?? $sourceKey . '_row_') . $index,
            'event_token_from_field' => $this->resolveEventTokenFromField($normalized, $payload, $index),
            'combine_datetime' => $this->combineDateTime(
                $this->rowValue($row, (string) ($payload['date_field'] ?? '')),
                $this->rowValue($row, (string) ($payload['time_field'] ?? '')),
            ),
            'first_non_null' => $this->resolveFirstNonNull($normalized, (array) ($payload['fields'] ?? [])),
            'now' => now(),
            'is_history_by_view_mode' => $viewMode === 'history',
            default => null,
        };
    }

    private function resolveEventTokenFromField(array $normalized, array $payload, int $index): string
    {
        $field = (string) ($payload['field'] ?? 'event_token');
        $value = trim((string) ($normalized[$field] ?? ''));

        if ($value !== '') {
            return $value;
        }

        return (string) ($payload['fallback_prefix'] ?? 'row_') . $index;
    }

    private function resolveFirstNonNull(array $normalized, array $fields)
    {
        foreach ($fields as $field) {
            $value = $normalized[(string) $field] ?? null;

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function buildLaneMetrics(Collection $group, array $blueprint, int $totalBoxes): array
    {
        $metrics = collect($this->stageKeys())
            ->map(function (string $stage) use ($group, $blueprint) {
                $stageRows = $group->filter(fn(array $row) => $this->rowEnteredStage($row, $stage))->values();
                $pendingRows = $this->resolveStagePendingRows($stage, $group);
                $startAt = $this->resolveStageBoundaryTimestamp($stage, $group, 'start', $stageRows);
                $endAt = $this->resolveStageBoundaryTimestamp($stage, $group, 'end', $stageRows);
                $pendingAt = $pendingRows
                    ->map(fn(array $row) => $this->stagePendingTimestamp($row, $stage))
                    ->filter()
                    ->max(fn(Carbon $timestamp) => $timestamp->timestamp);
                $hasRows = $stageRows->isNotEmpty();
                $hasPendingRows = $pendingRows->isNotEmpty();
                $stageQty = $hasRows
                    ? $this->resolveStageQuantity($stage, $stageRows)
                    : ($hasPendingRows ? $this->countUniqueEventTokens($pendingRows) : null);
                $breakdown = $this->buildLaneBreakdown($stage, $stageRows);
                $pendingStartAt = $hasPendingRows ? $this->resolveStagePendingBoundaryTimestamp($stage, $pendingRows, 'min') : null;
                $pendingEndAt = $hasPendingRows ? $this->resolveStagePendingBoundaryTimestamp($stage, $pendingRows, 'max') : null;
                $displayStartAt = $startAt ?? $pendingStartAt;
                $displayEndAt = $endAt ?? ($hasRows ? null : $pendingEndAt);
                $activityTimestamp = collect([
                    $displayEndAt?->timestamp,
                    $displayStartAt?->timestamp,
                    $pendingAt,
                ])->filter()->max();

                return [
                    'column_key' => $stage,
                    'qty' => $stageQty,
                    'entry_breakdown' => $breakdown['entry_breakdown'],
                    'completion_breakdown' => $breakdown['completion_breakdown'],
                    'unit' => (string) ($this->stageDefinition($stage)['summaryLabel'] ?? 'Box'),
                    'suhu' => $this->defaultTemperature($stage, (int) ($stageQty ?? 0)),
                    'jam_awal' => $displayStartAt ? $displayStartAt->format('d/m H:i') : null,
                    'jam_akhir' => $displayEndAt ? $displayEndAt->format('d/m H:i') : null,
                    'pending_at' => $pendingAt ? Carbon::createFromTimestamp($pendingAt)->format('d/m H:i') : null,
                    'activity_timestamp' => $activityTimestamp,
                    'status' => self::LANE_STATUS_WAIT,
                    'progress_state' => self::LANE_PROGRESS_NOT_STARTED,
                    'has_rows' => $hasRows,
                    'has_pending_rows' => $hasPendingRows,
                    'is_applicable' => $this->stageIsApplicable($stage, $blueprint),
                ];
            })
            ->values();

        return $this->applyBlueprintStageState($metrics, $blueprint)->all();
    }

    private function applyBlueprintStageState(Collection $metrics, array $blueprint): Collection
    {
        $rawLanes = $metrics->keyBy('column_key');
        $resolvedLanes = [];

        foreach ($this->stageKeys() as $stage) {
            $lane = $rawLanes->get($stage);

            if (!$lane) {
                continue;
            }

            $resolvedCollection = collect($resolvedLanes);
            $dependenciesSatisfied = $this->stageDependenciesSatisfied($stage, $resolvedCollection, $blueprint);
            $completed = false;

            if ((bool) ($lane['is_applicable'] ?? false)) {
                $completionResult = $this->stageMeetsCompletionPolicy($stage, $lane, $rawLanes, $blueprint);
                $completed = ((bool) ($completionResult['completed'] ?? false)) && $dependenciesSatisfied;
            }

            if (!(bool) ($lane['has_rows'] ?? false) && !(bool) ($lane['has_pending_rows'] ?? false)) {
                $fallbackQty = $this->resolveFallbackMergeQuantity($stage, $resolvedCollection, $blueprint);

                if ($fallbackQty !== null) {
                    $lane['qty'] = $fallbackQty;
                    $lane['suhu'] = $this->defaultTemperature($stage, $fallbackQty);
                }
            }

            if ($completed) {
                $lane['progress_state'] = self::LANE_PROGRESS_COMPLETED;
                $lane['status'] = self::LANE_STATUS_DONE;
            } elseif ((bool) ($lane['has_rows'] ?? false)) {
                $lane['progress_state'] = self::LANE_PROGRESS_IN_PROGRESS;
                $lane['status'] = self::LANE_STATUS_RUNNING;
            } elseif ((bool) ($lane['has_pending_rows'] ?? false)) {
                $lane['progress_state'] = self::LANE_PROGRESS_PENDING;
                $lane['status'] = self::LANE_STATUS_PENDING;
            } else {
                $lane['progress_state'] = self::LANE_PROGRESS_NOT_STARTED;
                $lane['status'] = self::LANE_STATUS_WAIT;
            }

            unset($lane['has_rows'], $lane['has_pending_rows'], $lane['is_applicable']);

            $resolvedLanes[$stage] = $lane;
        }

        return collect(array_values($resolvedLanes));
    }

    private function stageMeetsCompletionPolicy(
        string $stage,
        array $lane,
        Collection $lanesByStage,
        array $blueprint,
    ): array {
        $policy = $this->stageCompletionPolicy($stage);
        $context = $this->stageCompletionEvaluationContext($stage, $lane, $lanesByStage, $blueprint);

        foreach ($policy['conditions'] ?? [] as $condition) {
            $matched = $this->stageCompletionConditionMatches($condition, $context);

            if ($matched === true) {
                return [
                    'completed' => true,
                    'matched_condition' => (string) ($condition['type'] ?? ''),
                ];
            }
        }

        if (!empty($policy['conditions'] ?? [])) {
            return [
                'completed' => false,
                'matched_condition' => null,
            ];
        }

        $completedByFallback = $this->stageMatchesCompletionRulesFallback($stage, $lane);

        return [
            'completed' => $completedByFallback,
            'matched_condition' => $completedByFallback ? 'completion_rules' : null,
        ];
    }

    private function stageCompletionEvaluationContext(
        string $stage,
        array $lane,
        Collection $lanesByStage,
        array $blueprint,
    ): array {
        return [
            'stage' => $stage,
            'lane' => $lane,
            'lanes_by_stage' => $lanesByStage,
            'blueprint' => $blueprint,
            'stage_qty' => array_key_exists('qty', $lane) && $lane['qty'] !== null ? (int) $lane['qty'] : null,
            'entry_count' => $this->laneBreakdownCount($lane, 'entry_breakdown'),
            'completion_count' => $this->laneBreakdownCount($lane, 'completion_breakdown'),
            'has_rows' => (bool) ($lane['has_rows'] ?? false),
        ];
    }

    private function stageCompletionConditionMatches(array $condition, array $context): ?bool
    {
        return match ((string) ($condition['type'] ?? '')) {
            'balance' => $this->completionConditionMatchesBalance($condition, $context),
            'compare_stage_quantity' => $this->completionConditionMatchesStageQuantity($condition, $context),
            'dependent_entry_exists' => $this->completionConditionMatchesDependentEntry($condition, $context),
            default => null,
        };
    }

    private function completionConditionMatchesBalance(array $condition, array $context): bool
    {
        $entryCount = (int) ($context['entry_count'] ?? 0);
        $completionCount = (int) ($context['completion_count'] ?? 0);

        return $entryCount > 0 && $completionCount >= $entryCount;
    }

    private function completionConditionMatchesStageQuantity(array $condition, array $context): ?bool
    {
        $targetStage = (string) ($condition['stage'] ?? '');
        $blueprint = (array) ($context['blueprint'] ?? []);
        $lanesByStage = $context['lanes_by_stage'] instanceof Collection
            ? $context['lanes_by_stage']
            : collect((array) ($context['lanes_by_stage'] ?? []));

        if ($targetStage === '' || !$this->stageIsApplicable($targetStage, $blueprint)) {
            return null;
        }

        $targetLane = $lanesByStage->get($targetStage);
        $currentQty = $context['stage_qty'] ?? null;
        $targetQty = $targetLane && ($targetLane['qty'] ?? null) !== null ? (int) $targetLane['qty'] : null;

        if ($currentQty === null || $targetQty === null) {
            return false;
        }

        return $this->compareNumericValues($currentQty, $targetQty, (string) ($condition['operator'] ?? 'gte'));
    }

    private function completionConditionMatchesDependentEntry(array $condition, array $context): ?bool
    {
        $blueprint = (array) ($context['blueprint'] ?? []);
        $lanesByStage = $context['lanes_by_stage'] instanceof Collection
            ? $context['lanes_by_stage']
            : collect((array) ($context['lanes_by_stage'] ?? []));
        $targetStages = collect((array) ($condition['stages'] ?? []))
            ->filter(fn(string $stage) => $this->stageIsApplicable($stage, $blueprint))
            ->values();

        if ($targetStages->isEmpty()) {
            return null;
        }

        $hasEntryByStage = $targetStages->map(function (string $targetStage) use ($lanesByStage) {
            $targetLane = $lanesByStage->get($targetStage);

            return $targetLane && (bool) ($targetLane['has_rows'] ?? false);
        });

        return ((string) ($condition['match'] ?? 'any')) === 'all'
            ? $hasEntryByStage->every(fn(bool $hasEntry) => $hasEntry === true)
            : $hasEntryByStage->contains(true);
    }

    private function stageMatchesCompletionRulesFallback(string $stage, array $lane): bool
    {
        if (!(bool) ($lane['has_rows'] ?? false)) {
            return false;
        }

        $completionRules = $this->stagePolicyRules($stage, 'completion_rules');

        // dd($stage, $completionRules);
        if (empty($completionRules)) {
            return false;
        }

        return $this->laneBreakdownCount($lane, 'completion_breakdown') > 0;
    }

    private function laneBreakdownCount(array $lane, string $key): int
    {
        return (int) collect($lane[$key] ?? [])->sum('count');
    }

    private function compareNumericValues(int $left, int $right, string $operator): bool
    {
        return match ($operator) {
            'gt' => $left > $right,
            'eq' => $left === $right,
            'lte' => $left <= $right,
            'lt' => $left < $right,
            default => $left >= $right,
        };
    }

    private function stageDependenciesSatisfied(string $stage, Collection $resolvedLanes, array $blueprint): bool
    {
        $applicableDependencies = collect($this->stageDependencies($stage, $blueprint))
            ->filter(fn(string $dependencyStage) => $this->stageIsApplicable($dependencyStage, $blueprint))
            ->values();

        if ($applicableDependencies->isEmpty()) {
            return true;
        }

        if ($this->stageMergePolicy($stage, $blueprint) !== self::MERGE_POLICY_ALL_DEPENDENCIES) {
            return true;
        }

        return $applicableDependencies->every(function (string $dependencyStage) use ($resolvedLanes) {
            $dependencyLane = $resolvedLanes->get($dependencyStage);

            return $dependencyLane && ($dependencyLane['progress_state'] ?? '') === 'completed';
        });
    }

    private function resolveFallbackMergeQuantity(string $stage, Collection $resolvedLanes, array $blueprint): ?int
    {
        $applicableDependencies = collect($this->stageDependencies($stage, $blueprint))
            ->filter(fn(string $dependencyStage) => $this->stageIsApplicable($dependencyStage, $blueprint))
            ->values();

        if ($applicableDependencies->count() < 2) {
            return null;
        }

        $qty = $applicableDependencies->sum(function (string $dependencyStage) use ($resolvedLanes) {
            $dependencyLane = $resolvedLanes->get($dependencyStage);

            if (!$dependencyLane || ($dependencyLane['progress_state'] ?? '') !== 'completed') {
                return 0;
            }

            return (int) ($dependencyLane['qty'] ?? 0);
        });

        return $qty > 0 ? $qty : null;
    }

    // FNC MAPPING NE
    private function rowEnteredStage(array $row, string $stage): bool
    {
        if (!$this->rowMatchesStageSource($row, $stage)) {
            return false;
        }

        return $this->matchesStageRules($row, $this->stageRules($stage, 'entry_rules'));
    }

    private function rowCompletedStage(array $row, string $stage): bool
    {
        if (!$this->rowMatchesStageSource($row, $stage)) {
            return false;
        }

        return $this->matchesStageRules($row, $this->stageRules($stage, 'completion_rules'));
    }

    private function rowPendingStage(array $row, string $stage): bool
    {
        if (!$this->rowMatchesStageSource($row, $stage)) {
            return false;
        }

        return $this->matchesStageRules($row, $this->stageRules($stage, 'pending_rules'));
    }

    private function rowMatchesStageSource(array $row, string $stage): bool
    {
        $sourceKeys = $this->stageSourceKeys($stage);

        if (empty($sourceKeys)) {
            return true;
        }

        return in_array((string) ($row['source_key'] ?? ''), $sourceKeys, true);
    }

    private function stageTimeConfig(string $stage, string $boundary): array
    {
        $definition = $this->stageDefinition($stage);
        $configured = is_array($definition['time'][$boundary] ?? null)
            ? $definition['time'][$boundary]
            : [];
        $isConfigured = !empty($configured);
        $defaults = $boundary === 'end'
            ? [
                'rules' => 'completion_rules',
                'timestamp' => null,
                'aggregate' => 'max',
                'fallback_to_entry' => true,
            ]
            : [
                'rules' => 'entry_rules',
                'timestamp' => null,
                'aggregate' => 'min',
                'fallback_to_entry' => false,
            ];

        $config = array_replace($defaults, $configured);
        $config['rules'] = in_array($config['rules'], ['entry_rules', 'completion_rules'], true)
            ? $config['rules']
            : $defaults['rules'];
        $config['aggregate'] = in_array($config['aggregate'], ['min', 'max'], true)
            ? $config['aggregate']
            : $defaults['aggregate'];

        if ($isConfigured && !array_key_exists('fallback_to_entry', $configured)) {
            $config['fallback_to_entry'] = false;
        }

        $config['is_configured'] = $isConfigured;

        return $config;
    }

    private function resolveStageBoundaryTimestamp(
        string $stage,
        Collection $rows,
        string $boundary,
        ?Collection $defaultRows = null,
    ): ?Carbon {
        $config = $this->stageTimeConfig($stage, $boundary);
        $rules = $this->stageRules($stage, (string) ($config['rules'] ?? ''));
        $candidateRows = (bool) ($config['is_configured'] ?? false) || $defaultRows === null
            ? $rows
            : $defaultRows;
        $matchedRows = $this->stageRowsMatchingRules($stage, $candidateRows, $rules);
        $timestampField = trim((string) ($config['timestamp'] ?? ''));
        $timestamps = $matchedRows
            ->map(function (array $row) use ($rules, $timestampField) {
                if ($timestampField !== '') {
                    return $row[$timestampField] ?? null;
                }

                $matchedRule = $this->firstMatchingStageRule($row, $rules);

                if (!$matchedRule) {
                    return null;
                }

                return $row[$matchedRule['timestamp'] ?? 'pairing_at'] ?? null;
            })
            ->filter();

        if ($timestamps->isEmpty() && (bool) ($config['fallback_to_entry'] ?? false)) {
            return $this->resolveStageBoundaryTimestamp($stage, $rows, 'start', $defaultRows);
        }

        if ($timestamps->isEmpty()) {
            return null;
        }

        $resolvedTimestamp = (string) ($config['aggregate'] ?? 'max') === 'min'
            ? $timestamps->min(fn(Carbon $timestamp) => $timestamp->timestamp)
            : $timestamps->max(fn(Carbon $timestamp) => $timestamp->timestamp);

        return Carbon::createFromTimestamp($resolvedTimestamp);
    }

    private function stageRowsMatchingRules(string $stage, Collection $rows, array $rules): Collection
    {
        if (empty($rules)) {
            return collect();
        }

        return $rows
            ->filter(function (array $row) use ($stage, $rules) {
                return $this->rowMatchesStageSource($row, $stage) && $this->matchesStageRules($row, $rules);
            })
            ->values();
    }

    private function stageEntryTimestamp(array $row, string $stage): ?Carbon
    {
        $matchedRule = $this->firstMatchingStageRule($row, $this->stageRules($stage, 'entry_rules'));

        if (!$matchedRule) {
            return null;
        }

        return $row[$matchedRule['timestamp'] ?? 'pairing_at'] ?? null;
    }

    private function stagePendingTimestamp(array $row, string $stage): ?Carbon
    {
        $matchedRule = $this->firstMatchingStageRule($row, $this->stageRules($stage, 'pending_rules'));

        if (!$matchedRule) {
            return null;
        }

        return $row[$matchedRule['timestamp'] ?? 'pairing_at'] ?? null;
    }

    private function resolveStagePendingRows(string $stage, Collection $rows): Collection
    {
        return $rows
            ->filter(fn(array $row) => $this->rowPendingStage($row, $stage))
            ->values();
    }

    private function resolveStagePendingBoundaryTimestamp(string $stage, Collection $rows, string $aggregate): ?Carbon
    {
        $timestamps = $rows
            ->map(fn(array $row) => $this->stagePendingTimestamp($row, $stage))
            ->filter();

        if ($timestamps->isEmpty()) {
            return null;
        }

        $timestamp = $aggregate === 'min'
            ? $timestamps->min(fn(Carbon $timestamp) => $timestamp->timestamp)
            : $timestamps->max(fn(Carbon $timestamp) => $timestamp->timestamp);

        return Carbon::createFromTimestamp($timestamp);
    }

    private function stageLatestTimestamp(array $row, string $stage): ?Carbon
    {
        $matchedRule = $this->firstMatchingStageRule($row, $this->stageRules($stage, 'completion_rules'));

        if ($matchedRule) {
            $timestamp = $row[$matchedRule['timestamp'] ?? 'in_at'] ?? null;

            if ($timestamp) {
                return $timestamp;
            }
        }

        return $this->stageEntryTimestamp($row, $stage);
    }

    private function matchesStageRules(array $row, array $rules): bool
    {
        return $this->firstMatchingStageRule($row, $rules) !== null;
    }

    private function firstMatchingStageRule(array $row, array $rules): ?array
    {
        foreach ($rules as $rule) {
            if ($this->matchesStageRule($row, $rule)) {
                return $rule;
            }
        }

        return null;
    }

    private function matchesStageRule(array $row, array $rule): bool
    {
        $field = $rule['field'] ?? null;
        $operator = $rule['operator'] ?? 'equals';
        $value = $rule['value'] ?? null;
        $source = $field !== null ? (string) ($row[$field] ?? '') : '';
        
        // dd($row, $rule, $source);

        // if($rule['field'] == 'flag_hasil_produksi_gr' && $row['prd'] == 'PR0425-00028-1'){
        //     dd($row, $rule, $source);
        // }

        if (in_array($operator, ['equals', 'contains', 'starts_with'], true) && is_array($value)) {
            return false;
        }
        

        $result = match ($operator) {
            'equals' => $source === (string) $value,
            'contains' => $value !== null && str_contains($source, (string) $value),
            'in' => in_array($source, (array) $value, true),
            'starts_with' => $value !== null && str_starts_with($source, (string) $value),
            'not_null' => !blank($row[$field] ?? null),
            default => false,
        };
        // if($rule['field'] == 'flag_hasil_produksi_gr' && $row['prd'] == 'PR0425-00028-1'){
        //     dd($row, $rule, $source,$result);
        // }
        return $result;
    }

    private function resolveLastActivityDate(Collection $group): Carbon
    {
        $timestamps = $group
            ->flatMap(function (array $row) {
                // Mengambil semua timestamp yang ada (jam awal/jam akhir dari semua mesin)
                return array_filter([
                    $row['pairing_at'],
                    $row['in_at'],
                    $row['source_at'] ?? null,
                    $row['hasil_produksi_gr_at'] ?? null,
                ]);
            })
            ->filter();

        return $timestamps->isEmpty()
            ? now()
            : Carbon::createFromTimestamp($timestamps->max(fn(Carbon $timestamp) => $timestamp->timestamp));
    }

    private function resolveRecordDate(Collection $group): string
    {
        $timestamps = $group
            ->flatMap(function (array $row) {
                return array_filter([
                    $row['pairing_at'],
                    $row['in_at'],
                    $row['source_at'] ?? null,
                    $row['hasil_produksi_gr_at'] ?? null,
                ]);
            })
            ->filter();

        return $timestamps->isEmpty()
            ? now()->format('Y-m-d')
            : Carbon::createFromTimestamp($timestamps->min(fn(Carbon $timestamp) => $timestamp->timestamp))->format(
                'Y-m-d',
            );
    }

    private function combineDateTime($date, $time): ?Carbon
    {
        if (blank($date) || blank($time)) {
            return null;
        }

        try {
            $datePart = Carbon::parse($date)->format('Y-m-d');
            $timePart = Carbon::parse($time)->format('H:i:s');

            return Carbon::parse($datePart . ' ' . $timePart);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function resolveStageQuantity(string $stage, Collection $stageRows): int
    {
        if ($stageRows->isEmpty()) {
            return 0;
        }

        $quantity = $this->stageQuantityConfig($stage);
        $mode = (string) ($quantity['mode'] ?? 'unique_event_token');
        $sourceKey = (string) ($quantity['source_key'] ?? '');

        if ($mode === 'row_count') {
            if ($sourceKey !== '') {
                return $stageRows
                    ->filter(fn(array $row) => (string) ($row['source_key'] ?? '') === $sourceKey)
                    ->count();
            }

            return $stageRows->count();
        }
        // dd($stage, $mode, $sourceKey, $stageRows->all());
        if ($mode === 'sum') {

            $field = (string) ($quantity['field'] ?? '');
            // if($stageRows->first()['prd_order'] == 'PRD0426-00022'){

            //     dd($stage, $mode, $sourceKey, $field, $stageRows);
            // }
            return $stageRows
                ->filter(fn(array $row) =>
                    $sourceKey === '' || (string) ($row['source_key'] ?? '') === $sourceKey
                )
                ->sum(fn(array $row) => (int) ($row[$field] ?? 0));
        }

        if ($mode === 'matched_rules') {
            $rules = (array) ($quantity['rules'] ?? []);

            return $this->countUniqueEventTokensByRules($stageRows, $rules);
        }

        return $stageRows->pluck('event_token')->filter()->unique()->count();
    }

    private function isRecordClosed(array $lanes, array $blueprint): bool
    {
        $lanesCollection = collect($lanes);
        $applicableStages = collect($blueprint['active_stages'] ?? [])->values();

        if ($this->recordCompletionPolicy($blueprint) === self::RECORD_CLOSE_POLICY_ALL_APPLICABLE_STAGES) {
            return $applicableStages->isNotEmpty() && $applicableStages->every(function (string $stage) use ($lanesCollection) {
                $lane = $lanesCollection->firstWhere('column_key', $stage);

                return ($lane['progress_state'] ?? null) === 'completed';
            });
        }

        $finalStageKey = collect($this->stageKeysReversed())->first(
            fn(string $stage) => in_array($stage, $applicableStages->all(), true),
        );

        if (!$finalStageKey) {
            return false;
        }

        $finalLane = $lanesCollection->firstWhere('column_key', $finalStageKey);

        return ($finalLane['progress_state'] ?? null) === 'completed';
    }

    private function normalizeLocation(?string $value): string
    {
        return strtoupper(trim((string) $value));
    }

    private function formatBatch(?string $batch): string
    {
        $batch = trim((string) $batch);

        if ($batch === '') {
            return 'Tanpa Batch';
        }

        return str_starts_with(strtolower($batch), 'batch') ? $batch : 'Batch ' . $batch;
    }

    private function formatBatchSummary(Collection $group): string
    {
        $batches = $group->pluck('batch')->filter()->unique()->values();

        if ($batches->isEmpty()) {
            return 'Tanpa Batch';
        }

        if ($batches->count() === 1) {
            return $this->formatBatch($batches->first());
        }

        return 'Batch ' . $batches->implode(', ');
    }

    private function normalizeGroupBatch(?string $batch): string
    {
        $normalized = trim((string) $batch);

        return $normalized === '' ? 'Tanpa Batch' : $normalized;
    }

    private function recordCompositeId(string $noSplit, string $batch): string
    {
        return $noSplit . '|' . $batch;
    }

    private function recordGroupKey(array $row): string
    {
        $noSplit = trim((string) ($row['prd'] ?? ''));
        $batch = $this->normalizeGroupBatch($row['batch'] ?? null);

        return $this->recordCompositeId($noSplit, $batch);
    }

    private function defaultTemperature(string $stage, int $qty): ?int
    {
        if ($qty <= 0) {
            return null;
        }

        $definition = $this->stageDefinition($stage);
        $temperature = $definition['temperature'] ?? null;

        if (is_numeric($temperature)) {
            return (int) $temperature;
        }

        return null;
    }

    private function buildUnionQuery(array $tables, string $alias, array $definition)
    {
        $queries = [];

        foreach ($tables as $table) {
            $q = DB::table($table . ' as ' . $alias);
            $this->applySourceJoins($q, $definition['joins'] ?? []);
            if (!empty($definition['where'])) {
                $q->where($definition['where']);
            }
            $q->select($definition['select'] ?? []);

            $queries[] = $q;
        }

        // Ambil query pertama
        $base = array_shift($queries);

        // UNION ALL sisanya
        foreach ($queries as $q) {
            $base->unionAll($q);
        }

        return $base;
    }

    private function dateRange(array $records): array
    {
        $dates = collect($records)->pluck('date')->filter()->values();

        return [
            'from' => $dates->min() ?? now()->format('Y-m-d'),
            'to' => $dates->max() ?? now()->format('Y-m-d'),
        ];
    }
}
