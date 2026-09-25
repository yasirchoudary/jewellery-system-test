<template>
    <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-12 mt-3">
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">{{ $t('stock.title') }}</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-2">
                                            <label class="text-md">{{ $t('stock.metalFilter') }}</label>
                                            <select v-model="filters.metal_type" class="form-control" @change="loadLedger">
                                                <option value="">{{ $t('common.all') }}</option>
                                                <option value="gold">{{ $t('common.gold') }}</option>
                                                <option value="silver">{{ $t('common.silver') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="text-md">{{ $t('stock.txnFilter') }}</label>
                                            <select v-model="filters.transaction_type" class="form-control" @change="loadLedger">
                                                <option value="">{{ $t('common.all') }}</option>
                                                <option value="purchase">{{ $t('stock.purchase') }}</option>
                                                <option value="sale">{{ $t('stock.sale') }}</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="text-md">{{ $t('common.perPage') }}</label>
                                            <select v-model="filters.paginate" class="form-control" @change="loadLedger">
                                                <option value="10">10</option>
                                                <option value="20">20</option>
                                                <option value="50">50</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <div class="small-box bg-warning">
                                                <div class="inner">
                                                    <h3>{{ balances.gold }}<sup style="font-size:14px">g</sup></h3>
                                                    <p>{{ $t('stock.goldInStock') }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="small-box bg-secondary">
                                                <div class="inner">
                                                    <h3>{{ balances.silver }}<sup style="font-size:14px">g</sup></h3>
                                                    <p>{{ $t('stock.silverInStock') }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="small-box bg-info">
                                                <div class="inner">
                                                    <h3>{{ fineGoldTotal }}<sup style="font-size:14px">g</sup></h3>
                                                    <p>Fine Gold Equivalent</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="small-box bg-success">
                                                <div class="inner">
                                                    <h3>Rs. {{ totalStockValue }}</h3>
                                                    <p>Est. Inventory Value</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <h5 class="mt-2">{{ $t('stock.byItemType') }}</h5>
                                    <div class="table-responsive mb-3">
                                    <table class="table table-bordered table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>{{ $t('common.itemType') }}</th>
                                                <th>{{ $t('common.metal') }}</th>
                                                <th class="text-right">{{ $t('common.weightG') }}</th>
                                                <th class="text-right">{{ $t('common.pieces') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="row in byQuality" :key="row.sell_quality_id">
                                                <td>{{ $label(row.quality_name) }}</td>
                                                <td>{{ row.metal_type ? $label(row.metal_type) : '-' }}</td>
                                                <td class="text-right">{{ row.weight_grams }}</td>
                                                <td class="text-right">{{ row.pieces }}</td>
                                            </tr>
                                            <tr v-if="!byQuality.length">
                                                <td colspan="4" class="text-center text-muted">{{ $t('stock.noItemStock') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    </div>

                                    <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-sm">
                                        <thead class="text-md">
                                            <tr>
                                                <th>{{ $t('common.date') }}</th>
                                                <th>{{ $t('common.metal') }}</th>
                                                <th>{{ $t('stock.item') }}</th>
                                                <th>Tag / Barcode</th>
                                                <th>{{ $t('common.type') }}</th>
                                                <th class="text-right">Gross (g)</th>
                                                <th class="text-right">Stone (g)</th>
                                                <th class="text-right">Net Wt (g)</th>
                                                <th class="text-center">Karat</th>
                                                <th class="text-right">Fine Gold (g)</th>
                                                <th class="text-right">{{ $t('common.pieces') }}</th>
                                                <th class="text-right">{{ $t('common.rateG') }}</th>
                                                <th class="text-right">{{ $t('common.amount') }}</th>
                                                <th class="text-right">{{ $t('stock.balanceAfter') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-md">
                                            <tr v-for="row in ledger.data" :key="row.stock_ledger_id">
                                                <td>{{ formatDate(row.created_at) }}</td>
                                                <td>{{ $label(row.metal_type) }}</td>
                                                <td>{{ row.item ? $label(row.item.quality_name) : '-' }}</td>
                                                <td><span class="badge badge-light" v-if="row.tag_number">{{ row.tag_number }}</span><span v-else>-</span></td>
                                                <td>{{ $label(row.transaction_type) }}</td>
                                                <td class="text-right">{{ row.gross_weight > 0 ? row.gross_weight : row.weight_grams }}</td>
                                                <td class="text-right">{{ row.stone_weight > 0 ? row.stone_weight : '0.000' }}</td>
                                                <td class="text-right font-weight-bold">{{ row.net_weight > 0 ? row.net_weight : row.weight_grams }}</td>
                                                <td class="text-center"><span class="badge badge-secondary">{{ row.purity_karat ? row.purity_karat + 'K' : '22K' }}</span></td>
                                                <td class="text-right text-success font-weight-bold">{{ row.fine_weight > 0 ? row.fine_weight : (row.weight_grams * 22 / 24).toFixed(3) }}</td>
                                                <td class="text-right">{{ row.quantity_pieces }}</td>
                                                <td class="text-right">{{ row.rate_per_gram || '-' }}</td>
                                                <td class="text-right">{{ row.amount || '-' }}</td>
                                                <td class="text-right">{{ row.balance_weight_after }}</td>
                                            </tr>
                                            <tr v-if="!ledger.data || ledger.data.length === 0">
                                                <td colspan="14" class="text-center text-muted">{{ $t('stock.noMovements') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-sm-6 offset-5">
                                            <pagination :data="ledger" @pagination-change-page="loadLedger"></pagination>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
</template>

<script>
export default {
    name: 'StockLedger',
    data() {
        return {
            ledger: { data: [] },
            balances: { gold: '0.000', silver: '0.000' },
            byQuality: [],
            valuation: null,
            filters: {
                metal_type: '',
                transaction_type: '',
                paginate: 20,
            },
        };
    },
    computed: {
        fineGoldTotal() {
            const gold = parseFloat(this.balances.gold || 0);
            return (gold * (22 / 24)).toFixed(3);
        },
        totalStockValue() {
            if (this.valuation && this.valuation.total_inventory_cost_value) {
                return Number(this.valuation.total_inventory_cost_value).toLocaleString();
            }
            return '0';
        }
    },
    mounted() {
        this.loadBalances();
        this.loadLedger();
    },
    methods: {
        loadBalances() {
            axios.get('/api/stock/balances').then((res) => {
                this.balances.gold = parseFloat(res.data.gold?.total_weight_grams || 0).toFixed(3);
                this.balances.silver = parseFloat(res.data.silver?.total_weight_grams || 0).toFixed(3);
                this.byQuality = (res.data.by_quality || []).filter((row) => row.weight_grams > 0 || row.pieces > 0);
            });
            axios.get('/api/inventory/valuation').then((res) => {
                if (res.data && res.data.summary) {
                    this.valuation = res.data.summary;
                }
            }).catch(() => {});
        },
        loadLedger(page = 1) {
            const params = new URLSearchParams({
                page,
                paginate: this.filters.paginate,
            });
            if (this.filters.metal_type) params.append('metal_type', this.filters.metal_type);
            if (this.filters.transaction_type) params.append('transaction_type', this.filters.transaction_type);

            axios.get('/api/stock/ledger?' + params.toString()).then((res) => {
                this.ledger = res.data;
            }).catch(() => {
                toastr.error(this.$t('stock.loadFail'));
            });
        },
        formatDate(value) {
            if (!value) return '';
            return value.substring(0, 10);
        },
    },
};
</script>
