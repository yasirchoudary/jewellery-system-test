<template>
  <div>
    <div class="card card-info mt-3">
      <div class="card-header">
        <h3 class="card-title">{{ $t('karigar.jobsTitle') }}</h3>
        <div class="card-tools d-flex align-items-center">
          <select class="form-control form-control-sm mr-2" v-model="statusFilter" @change="loadJobs">
            <option value="">{{ $t('karigar.allStatuses') }}</option>
            <option value="issued">{{ $t('karigar.statusIssued') }}</option>
            <option value="returned">{{ $t('karigar.statusReturned') }}</option>
          </select>
          <button type="button" class="btn btn-tool" data-card-widget="collapse">
            <i class="fas fa-minus"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead>
            <tr>
              <th>{{ $t('common.date') }}</th>
              <th>{{ $t('nav.karigar') }}</th>
              <th>{{ $t('common.metal') }}</th>
              <th class="text-right">{{ $t('karigar.issuedG') }}</th>
              <th class="text-right">{{ $t('karigar.returnedG') }}</th>
              <th>{{ $t('common.itemType') }}</th>
              <th class="text-right">{{ $t('common.mazduri') }}</th>
              <th>{{ $t('common.status') }}</th>
              <th width="220" class="text-center">{{ $t('common.action') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="job in jobs.data" :key="job.karigar_job_id">
              <td class="text-nowrap">{{ formatJobDate(job.job_date) }}</td>
              <td>{{ job.karigar ? job.karigar.karigar_name : '-' }}</td>
              <td>{{ $label(job.metal_type) }}</td>
              <td class="text-right">{{ job.issued_weight_grams }}</td>
              <td class="text-right">{{ job.returned_weight_grams || '-' }}</td>
              <td>{{ job.quality ? $label(job.quality.quality_name) : (job.item_description || '-') }}</td>
              <td class="text-right">{{ job.mazduri_cost ? 'Rs. ' + job.mazduri_cost : '-' }}</td>
              <td><span class="badge" :class="statusClass(job.job_status)">{{ job.job_status }}</span></td>
              <td class="text-center">
                <div class="table-actions">
                  <button
                    v-if="job.job_status === 'issued'"
                    type="button"
                    class="btn btn-sm btn-success table-actions__label"
                    @click="openAddOutward(job)"
                    :title="$t('karigar.tipMoreMetal')"
                  >
                    {{ $t('karigar.btnAddOutward') }}
                  </button>
                  <button
                    v-if="job.job_status === 'issued'"
                    type="button"
                    class="btn btn-sm btn-primary table-actions__label"
                    @click="openReturn(job)"
                  >
                    {{ $t('karigar.btnRecordInward') }}
                  </button>
                  <button
                    v-if="!job.invoice_mst_id"
                    type="button"
                    class="btn btn-sm btn-danger"
                    @click="deleteJob(job)"
                    :title="$t('karigar.tipDeleteRestore')"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                  <span v-else-if="job.invoice_mst_id" class="text-muted small">{{ $t('karigar.statusInvoiced') }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
        <pagination :data="jobs" @pagination-change-page="loadJobs"></pagination>
      </div>
    </div>

    <!-- Add more outward to existing job -->
    <div v-if="addOutwardJob" class="card card-success mt-3">
      <div class="card-header">
        <h3 class="card-title">
          {{ $t('karigar.addOutward', { name: addOutwardJob.karigar ? addOutwardJob.karigar.karigar_name : $t('nav.karigar') }) }}
        </h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="collapse">
            <i class="fas fa-minus"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">
          {{ $t('karigar.alreadyIssued') }}
          <strong>{{ addOutwardJob.issued_weight_grams }}g</strong>
          {{ $label(addOutwardJob.metal_type) }}
          <span v-if="addOutwardItemLabel !== '-'">
            ({{ $t('karigar.firstItem') }}: <strong>{{ addOutwardItemLabel }}</strong>)
          </span>
        </p>
        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ $t('common.itemType') }} <span style="color:red">*</span></label>
          <div class="col-md-4">
            <select class="form-control" v-model="addForm.sellQualityId" @change="onAddQualityChange">
              <option disabled value="">{{ $t('karigar.phItemStock') }}</option>
              <option
                v-for="opt in addQualityOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.text }}
              </option>
            </select>
            <small class="text-muted">{{ $t('karigar.helperDiffItem') }}</small>
          </div>
        </div>
        <p class="text-muted small mb-2" v-if="addStock">
          {{ $t('challan.inStockFor') }} <strong>{{ $label(addStock.quality_name) }}</strong>:
          {{ addStock.weight_grams }}g, {{ addStock.pieces }} {{ $t('common.pcs') }}
          <span v-if="addStock.available_piece_weights_label">
            ({{ addStock.available_piece_weights_label }})
          </span>
        </p>
        <p class="text-danger small mb-2" v-if="addStock && !addHasStock" role="alert">
          {{ $t('karigar.noStockItem') }}
        </p>
        <div class="mb-3" v-if="addWeightOptions.length">
          <span class="small text-muted mr-2">{{ $t('karigar.pickWeights') }}</span>
          <button
            v-for="opt in addWeightOptions"
            :key="opt.weight"
            type="button"
            class="btn btn-sm mr-1 mb-1"
            :class="isAddWeightSelected(opt.weight) ? 'btn-success' : 'btn-outline-secondary'"
            @click="selectAddWeight(opt)"
          >
            {{ opt.weight }}g
            <span v-if="opt.count > 1" class="badge badge-light ml-1">{{ opt.count }} {{ $t('common.pcs') }}</span>
          </button>
        </div>
        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ $t('karigar.weightPc') }} <span style="color:red">*</span></label>
          <div class="col-md-2">
            <input type="number" step="0.001" min="0.001" class="form-control text-right" v-model="addForm.weightPerPiece" :disabled="!addHasStock">
          </div>
          <label class="col-md-2 col-form-label">{{ $t('common.pieces') }} <span style="color:red">*</span></label>
          <div class="col-md-2">
            <input
              type="number"
              min="1"
              :max="addMaxPieces || undefined"
              class="form-control text-right"
              v-model.number="addForm.issuedPieces"
              :disabled="!addHasStock || addMaxPieces < 1"
            >
          </div>
          <label class="col-md-2 col-form-label">{{ $t('karigar.extraTotal') }}</label>
          <div class="col-md-2">
            <input type="text" class="form-control text-right" :value="addTotalWeightLabel" disabled>
          </div>
        </div>
        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ $t('common.notes') }}</label>
          <div class="col-md-10">
            <input type="text" class="form-control" v-model="addForm.notes" :placeholder="$t('karigar.phExtra')">
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-success" @click="submitAddOutward" :disabled="!addHasStock || addMaxPieces < 1 || Number(addForm.issuedPieces) > addMaxPieces">{{ $t('karigar.btnSaveExtra') }}</button>
        <button class="btn btn-secondary ml-2" @click="addOutwardJob = null">{{ $t('common.cancel') }}</button>
      </div>
    </div>

    <!-- Record inward -->
    <div v-if="returnJob" class="card card-warning mt-3">
      <div class="card-header">
        <h3 class="card-title">{{ $t('karigar.inwardReceive', { name: returnJob.karigar ? returnJob.karigar.karigar_name : $t('nav.karigar') }) }}</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="collapse">
            <i class="fas fa-minus"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">
          {{ $t('karigar.issuedLabel') }}
          <strong>{{ returnJob.issued_weight_grams }}g</strong>
          {{ $label(returnJob.metal_type) }}
          <span v-if="issuedSourceLabel"> {{ $t('karigar.fromLabel') }} <strong>{{ issuedSourceLabel }}</strong></span>
          <span v-if="itemToMakeLabel"> — {{ $t('karigar.itemToMake') }}: <strong>{{ itemToMakeLabel }}</strong></span>
        </p>
        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ $t('karigar.returnDate') }} <span style="color:red">*</span></label>
          <div class="col-md-4">
            <input type="datetime-local" class="form-control" v-model="returnForm.returnDate">
          </div>
          <label class="col-md-2 col-form-label">{{ $t('common.itemType') }} <span style="color:red">*</span></label>
          <div class="col-md-4">
            <select class="form-control" v-model="returnForm.sellQualityId">
              <option disabled value="">{{ $t('karigar.phItemType') }}</option>
              <option
                v-for="opt in qualityOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.text }}
              </option>
            </select>
            <small class="text-muted" v-if="inwardDefaultHint">
              {{ inwardDefaultHint }}
            </small>
          </div>
        </div>
        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ $t('karigar.returnedWeight') }} <span style="color:red">*</span></label>
          <div class="col-md-2">
            <input
              type="number"
              step="0.001"
              class="form-control text-right"
              v-model="returnForm.returnedWeightGrams"
              @input="onReturnedWeightInput"
            >
          </div>
          <label class="col-md-2 col-form-label">{{ $t('common.pieces') }}</label>
          <div class="col-md-2">
            <input type="number" min="1" class="form-control text-right" v-model="returnForm.returnedPieces">
          </div>
          <label class="col-md-2 col-form-label">{{ $t('karigar.wastage') }}</label>
          <div class="col-md-2">
            <input
              type="number"
              step="0.001"
              min="0"
              class="form-control text-right"
              v-model="returnForm.wastageGrams"
              @input="wastageManuallyEdited = true"
            >
            <small class="text-muted">{{ $t('karigar.helperWastage') }}</small>
          </div>
        </div>
        <div class="form-group row">
          <label class="col-md-2 col-form-label">{{ $t('common.mazduri') }}</label>
          <div class="col-md-4">
            <input type="number" step="0.01" min="0" class="form-control text-right" v-model="returnForm.mazduriCost">
            <small class="text-muted">{{ $t('karigar.helperMazduri') }}</small>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button class="btn btn-warning" @click="submitReturn">{{ $t('karigar.btnSaveInward') }}</button>
        <button class="btn btn-secondary ml-2" @click="returnJob = null">{{ $t('common.cancel') }}</button>
      </div>
    </div>
  </div>
</template>

<script>
import toastr from 'toastr';
import swal from 'sweetalert2';
import { getNowDateTime, formatDate } from '../../currency';

export default {
  name: 'SMKarigarJobs',
  data() {
    return {
      jobs: {},
      statusFilter: '',
      returnJob: null,
      addOutwardJob: null,
      addStock: null,
      qualities: [],
      wastageManuallyEdited: false,
      returnForm: {
        returnDate: getNowDateTime(),
        sellQualityId: '',
        returnedWeightGrams: '',
        returnedPieces: 1,
        wastageGrams: 0,
        mazduriCost: '',
      },
      addForm: {
        sellQualityId: '',
        weightPerPiece: '',
        issuedPieces: 1,
        notes: '',
      },
    };
  },
  computed: {
    issuedSourceLabel() {
      if (!this.returnJob) {
        return '';
      }
      if (this.returnJob.quality && this.returnJob.quality.quality_name) {
        return this.returnJob.quality.quality_name;
      }
      return '';
    },
    itemToMakeLabel() {
      if (!this.returnJob) {
        return '';
      }
      return (this.returnJob.item_description || '').trim();
    },
    inwardDefaultHint() {
      if (this.itemToMakeLabel) {
        return this.$t('karigar.defaultsToItemToMake', { item: this.itemToMakeLabel });
      }
      if (this.issuedSourceLabel) {
        return this.$t('karigar.defaultsToOutwardStock', { item: this.issuedSourceLabel });
      }
      return '';
    },
    addOutwardItemLabel() {
      if (!this.addOutwardJob) {
        return '-';
      }
      if (this.addOutwardJob.quality && this.addOutwardJob.quality.quality_name) {
        return this.addOutwardJob.quality.quality_name;
      }
      return this.addOutwardJob.item_description || '-';
    },
    qualityOptions() {
      const metal = this.returnJob && this.returnJob.metal_type
        ? String(this.returnJob.metal_type).toLowerCase()
        : '';

      let list = this.qualities;
      if (metal) {
        const sameMetal = list.filter(q => !q.metal_type || String(q.metal_type).toLowerCase() === metal);
        if (sameMetal.length) {
          list = sameMetal;
        }
      }

      return list.map(q => ({
        value: Number(q.sell_quality_id),
        text: q.quality_name,
      }));
    },
    addQualityOptions() {
      const metal = this.addOutwardJob && this.addOutwardJob.metal_type
        ? String(this.addOutwardJob.metal_type).toLowerCase()
        : '';

      let list = this.qualities;
      if (metal) {
        const sameMetal = list.filter(q => !q.metal_type || String(q.metal_type).toLowerCase() === metal);
        if (sameMetal.length) {
          list = sameMetal;
        }
      }

      return list.map(q => ({
        value: Number(q.sell_quality_id),
        text: q.quality_name,
      }));
    },
    addWeightOptions() {
      if (!this.addStock || !Array.isArray(this.addStock.available_piece_weights)) {
        return [];
      }
      const counts = {};
      this.addStock.available_piece_weights.forEach((w) => {
        const key = Number(w).toFixed(3);
        counts[key] = (counts[key] || 0) + 1;
      });
      return Object.keys(counts)
        .map(k => ({ weight: parseFloat(k), count: counts[k] }))
        .sort((a, b) => a.weight - b.weight);
    },
    addHasStock() {
      return !!(
        this.addStock &&
        Number(this.addStock.pieces) > 0 &&
        Array.isArray(this.addStock.available_piece_weights) &&
        this.addStock.available_piece_weights.length > 0
      );
    },
    addMaxPieces() {
      if (!this.addStock || !this.addForm.weightPerPiece) {
        return 0;
      }
      const per = parseFloat(this.addForm.weightPerPiece);
      return (this.addStock.available_piece_weights || []).filter(
        w => Math.abs(parseFloat(w) - per) <= 0.0005
      ).length;
    },
    addTotalWeightLabel() {
      const per = parseFloat(this.addForm.weightPerPiece || 0);
      const pcs = parseInt(this.addForm.issuedPieces || 0, 10);
      if (!(per > 0) || !(pcs > 0)) {
        return '';
      }
      return (per * pcs).toFixed(3);
    },
  },
  mounted() {
    this.loadJobs();
    this.loadQualities();
  },
  methods: {
    formatJobDate(d) { return formatDate(d); },
    statusClass(s) {
      return s === 'issued' ? 'badge-warning' : s === 'returned' ? 'badge-success' : 'badge-secondary';
    },
    loadJobs(page = 1) {
      axios.get('/api/karigar/jobs?page=' + page + '&paginate=10&status=' + this.statusFilter).then(res => {
        this.jobs = res.data;
      });
    },
    loadQualities() {
      return axios.get('/api/sellqualitycategories').then(catRes => {
        const cats = catRes.data.qualityCategories || [];
        const reqs = cats.map(c =>
          axios.get('/api/sellqualityofcategory/' + c.qualityCategoryId).then(res =>
            (res.data || []).map(q => ({
              ...q,
              metal_type: c.metalType || c.metal_type || null,
            }))
          )
        );
        return Promise.all(reqs).then(results => {
          this.qualities = results.flat();
        });
      });
    },
    resolveOutwardQualityId(job) {
      const raw = job.sell_quality_id
        || (job.quality && job.quality.sell_quality_id)
        || '';
      if (raw === '' || raw == null) {
        return '';
      }
      return Number(raw);
    },
    normalizeName(value) {
      return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/\s+/g, ' ');
    },
    resolveInwardQualityId(job) {
      const metal = job && job.metal_type ? String(job.metal_type).toLowerCase() : '';
      const itemToMake = this.normalizeName(job.item_description);

      if (itemToMake && this.qualities.length) {
        let candidates = this.qualities;
        if (metal) {
          const sameMetal = candidates.filter(
            q => !q.metal_type || String(q.metal_type).toLowerCase() === metal
          );
          if (sameMetal.length) {
            candidates = sameMetal;
          }
        }

        const exact = candidates.find(q => this.normalizeName(q.quality_name) === itemToMake);
        if (exact) {
          return Number(exact.sell_quality_id);
        }

        const partial = candidates.find((q) => {
          const name = this.normalizeName(q.quality_name);
          return name.includes(itemToMake) || itemToMake.includes(name);
        });
        if (partial) {
          return Number(partial.sell_quality_id);
        }
      }

      return this.resolveOutwardQualityId(job);
    },
    calcAutoWastage() {
      if (!this.returnJob) {
        return 0;
      }
      const issued = parseFloat(this.returnJob.issued_weight_grams || 0);
      const returned = parseFloat(this.returnForm.returnedWeightGrams || 0);
      if (!(issued > 0) || Number.isNaN(returned)) {
        return 0;
      }
      return Math.max(0, Math.round((issued - returned) * 1000) / 1000);
    },
    onReturnedWeightInput() {
      this.wastageManuallyEdited = false;
      this.returnForm.wastageGrams = this.calcAutoWastage();
    },
    openReturn(job) {
      this.addOutwardJob = null;
      const applyForm = () => {
        this.returnJob = job;
        this.wastageManuallyEdited = false;
        const returned = job.issued_weight_grams;
        this.returnForm = {
          returnDate: getNowDateTime(),
          sellQualityId: this.resolveInwardQualityId(job),
          returnedWeightGrams: returned,
          returnedPieces: 1,
          wastageGrams: 0,
          mazduriCost: '',
        };
        this.returnForm.wastageGrams = this.calcAutoWastage();
      };

      if (this.qualities.length) {
        applyForm();
        return;
      }

      this.loadQualities().then(applyForm).catch(() => {
        applyForm();
        toastr.warning(this.$t('karigar.loadTypesFailRetry'));
      });
    },
    openAddOutward(job) {
      this.returnJob = null;
      this.addOutwardJob = job;
      this.addStock = null;
      const start = () => {
        const defaultId = this.resolveOutwardQualityId(job);
        this.addForm = {
          sellQualityId: defaultId || '',
          weightPerPiece: '',
          issuedPieces: 1,
          notes: '',
        };
        this.preferAddQualityWithStock(defaultId);
      };

      if (this.qualities.length) {
        start();
        return;
      }

      this.loadQualities().then(start).catch(() => {
        start();
        toastr.warning(this.$t('karigar.loadTypesFailRetry'));
      });
    },
    preferAddQualityWithStock(preferredId) {
      const ids = [];
      if (preferredId) {
        ids.push(Number(preferredId));
      }
      this.addQualityOptions.forEach((opt) => {
        if (!ids.includes(Number(opt.value))) {
          ids.push(Number(opt.value));
        }
      });

      if (!ids.length) {
        return;
      }

      const tryNext = (index) => {
        if (index >= ids.length) {
          if (preferredId) {
            this.addForm.sellQualityId = preferredId;
            this.loadAddStock(preferredId);
          }
          return;
        }

        const id = ids[index];
        axios.get('/api/stock/quality/' + id).then((res) => {
          const weight = parseFloat(res.data.weight_grams || 0);
          const pieces = parseInt(res.data.pieces || 0, 10);
          if (weight > 0 && pieces > 0) {
            this.addForm.sellQualityId = id;
            this.addStock = res.data;
            return;
          }
          // Prefer first with stock; if preferred has stock we already returned above.
          // Keep scanning only until we find stock, but if preferred was first and empty, find another.
          tryNext(index + 1);
        }).catch(() => tryNext(index + 1));
      };

      tryNext(0);
    },
    onAddQualityChange() {
      this.addForm.weightPerPiece = '';
      this.addForm.issuedPieces = 1;
      this.addStock = null;
      if (this.addForm.sellQualityId) {
        this.loadAddStock(this.addForm.sellQualityId);
      }
    },
    loadAddStock(qualityId) {
      axios.get('/api/stock/quality/' + qualityId).then(res => {
        this.addStock = res.data;
      }).catch(() => {
        this.addStock = null;
        toastr.error(this.$t('karigar.loadStockForItemFail'));
      });
    },
    isAddWeightSelected(weight) {
      return Math.abs(parseFloat(this.addForm.weightPerPiece || 0) - weight) <= 0.0005;
    },
    selectAddWeight(opt) {
      this.addForm.weightPerPiece = opt.weight;
      this.addForm.issuedPieces = 1;
    },
    submitAddOutward() {
      if (!this.addOutwardJob) {
        return;
      }
      if (!this.addForm.sellQualityId) {
        toastr.info(this.$t('karigar.selectItemStock'));
        return;
      }
      if (!this.addHasStock) {
        toastr.error(this.$t('karigar.noStockItem'));
        return;
      }
      const per = parseFloat(this.addForm.weightPerPiece || 0);
      const pcs = parseInt(this.addForm.issuedPieces || 0, 10);
      if (!(per > 0) || !(pcs > 0)) {
        toastr.info(this.$t('karigar.weightPiecesRequired'));
        return;
      }
      if (this.addMaxPieces < 1 || pcs > this.addMaxPieces) {
        toastr.error(this.$t('karigar.onlyPieces', { n: this.addMaxPieces, weight: per }));
        return;
      }
      const total = Math.round(per * pcs * 1000) / 1000;
      axios.post('/api/karigar/jobs/' + this.addOutwardJob.karigar_job_id + '/add-issue', {
        sellQualityId: this.addForm.sellQualityId,
        issuedWeightGrams: total,
        issuedPieces: pcs,
        notes: this.addForm.notes || null,
      }).then(res => {
        if (res.data.status === 1) {
          swal.fire(this.$t('common.success'), res.data.message, 'success');
          this.addOutwardJob = null;
          this.loadJobs();
          this.$emit('job-changed');
        } else {
          toastr.error(res.data.message);
        }
      }).catch(err => toastr.error(err.response?.data?.message || this.$t('karigar.extraFail')));
    },
    submitReturn() {
      if (!this.returnJob || !this.returnForm.sellQualityId || !this.returnForm.returnedWeightGrams) {
        toastr.info(this.$t('karigar.returnRequired'));
        return;
      }
      axios.post('/api/karigar/jobs/' + this.returnJob.karigar_job_id + '/return', {
        returnDate: this.returnForm.returnDate,
        sellQualityId: this.returnForm.sellQualityId,
        returnedWeightGrams: this.returnForm.returnedWeightGrams,
        returnedPieces: this.returnForm.returnedPieces,
        wastageGrams: this.returnForm.wastageGrams,
        mazduriCost: this.returnForm.mazduriCost || 0,
      }).then(res => {
        if (res.data.status === 1) {
          swal.fire(this.$t('common.success'), res.data.message, 'success');
          this.returnJob = null;
          this.loadJobs();
          this.$emit('job-changed');
        } else {
          toastr.error(res.data.message);
        }
      }).catch(err => toastr.error(err.response?.data?.message || this.$t('karigar.returnFail')));
    },
    deleteJob(job) {
      swal.fire({
        title: this.$t('karigar.deleteJobConfirm'),
        html: this.$t('karigar.deleteJobText'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: this.$t('common.delete'),
      }).then(result => {
        if (!result.isConfirmed) {
          return;
        }
        axios.delete('/api/karigar/jobs/' + job.karigar_job_id).then(res => {
          if (res.data.status === 1) {
            swal.fire(this.$t('common.deleted'), res.data.message, 'success');
            if (this.returnJob && this.returnJob.karigar_job_id === job.karigar_job_id) {
              this.returnJob = null;
            }
            if (this.addOutwardJob && this.addOutwardJob.karigar_job_id === job.karigar_job_id) {
              this.addOutwardJob = null;
            }
            this.loadJobs();
            this.$emit('job-changed');
          } else {
            toastr.error(res.data.message);
          }
        }).catch(err => {
          const status = err.response?.status;
          const data = err.response?.data;
          const msg = data?.message
            || (data?.errors && Object.values(data.errors).flat()[0])
            || (status === 404 ? this.$t('karigar.deleteRouteNotFound') : null)
            || this.$t('karigar.deleteJobFail');
          toastr.error(msg);
        });
      });
    },
  },
};
</script>
