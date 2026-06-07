<?php

namespace App\Services\Sales;

use App\Repositories\Sales\SalesRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SalesService
{
    public const ALLOWED_BULK_FIELDS = [
        'JobCollection_Job_Setter_Full_Name',
        'JobCollection_Job_Closer_Full_Name',
        'JobCollection_Job_Admin_Full_Name',
        'JobCollection_Job_Stage',
        'JobCollection_Job_Status',
        'JobCollection_Job_Substatus',
    ];

    public function __construct(
        private SalesRepository $repo,
        private SalesQueryBuilder $queryBuilder,
        private SalesPostFilter $postFilter,
    ) {
    }

    public function dashboard(Request $request): LengthAwarePaginator
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        $query = $this->queryBuilder->build($request)->select(SalesRepository::DASHBOARD_LIST_FIELDS);

        // Fast path: no in-PHP (formula/version) filters → let Mongo sort + paginate.
        if (!$this->postFilter->hasPostFilters($request)) {
            $paginator = $query
                ->orderBy('JobCollection_Reception_Date', 'desc')
                ->orderBy('JobCollection_Customer_Full_Name', 'desc')
                ->paginate($perPage);

            $this->attachPhoneFlags($paginator->getCollection());
            return $paginator;
        }

        // Parity path (legacy applyFormulaFiltersAndPaginate): fetch the pre-filtered set,
        // apply formula/version predicates + sort in PHP, then paginate manually.
        $filtered = $this->sortRows(
            $this->postFilter->apply(
                $query->get(),
                $this->postFilter->collectFormulaFilters($request),
                $this->postFilter->collectVersionFilter($request),
            )
        );

        $page = max(1, (int) $request->query('page', 1));
        $pageItems = $filtered->slice(($page - 1) * $perPage, $perPage)->values();
        $this->attachPhoneFlags($pageItems);

        return new LengthAwarePaginator(
            $pageItems,
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    public function exportRows(Request $request): Collection
    {
        $query = $this->queryBuilder->build($request)->select(SalesRepository::EXPORT_SELECT_FIELDS);

        if (!$this->postFilter->hasPostFilters($request)) {
            return $query
                ->orderBy('JobCollection_Reception_Date', 'desc')
                ->orderBy('JobCollection_Customer_Full_Name', 'desc')
                ->limit(5000)
                ->get();
        }

        return $this->sortRows(
            $this->postFilter->apply(
                $query->get(),
                $this->postFilter->collectFormulaFilters($request),
                $this->postFilter->collectVersionFilter($request),
            )
        )->take(5000)->values();
    }

    private function sortRows(Collection $rows): Collection
    {
        // JobCollection_Reception_Date DESC, then JobCollection_Customer_Full_Name DESC
        // (legacy sorts in PHP after filtering to avoid Mongo's in-memory sort RAM limit).
        return $rows->sort(function ($a, $b) {
            $dateCmp = strcmp(
                (string) data_get($b, 'JobCollection_Reception_Date', ''),
                (string) data_get($a, 'JobCollection_Reception_Date', ''),
            );
            if ($dateCmp !== 0) {
                return $dateCmp;
            }
            return strcmp(
                (string) data_get($b, 'JobCollection_Customer_Full_Name', ''),
                (string) data_get($a, 'JobCollection_Customer_Full_Name', ''),
            );
        })->values();
    }

    private function attachPhoneFlags(Collection $items): void
    {
        foreach ($items as $item) {
            $item->Phone_Flag = $this->repo->countSamePhone($item->JobCollection_Customer_Phone) > 1;
        }
    }

    public function assignSetter(array $ids, string $setterName): int
    {
        return $this->repo->bulkUpdateByIds(
            $ids,
            ['JobCollection_Job_Setter_Full_Name' => $setterName],
            $this->queryBuilder->roleScopeFilter(),
        );
    }

    public function bulkAction(Request $request, array $rawFields, array $ids = [], array $filterQuery = []): int
    {
        $fields = [];
        foreach ($rawFields as $key => $value) {
            if (!in_array($key, self::ALLOWED_BULK_FIELDS, true)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $fields[$key] = $value;
        }
        if ($fields === []) {
            return 0;
        }

        if ($ids !== []) {
            return $this->repo->bulkUpdateByIds($ids, $fields, $this->queryBuilder->roleScopeFilter());
        }
        if ($filterQuery === []) {
            return 0;
        }

        // The filter-query path already runs through build(), which applies the role scope.
        $sub = Request::create('', 'GET', $filterQuery);
        $query = $this->queryBuilder->build($sub);

        // No formula/version filters → a single Mongo update covers the matched set.
        if (!$this->postFilter->hasPostFilters($sub)) {
            return (int) $query->update($fields);
        }

        // Legacy parity (DashboardsController::bulkAction): fetch the pre-filtered set, apply
        // formula/version predicates in PHP, then update exactly the rows that matched.
        $matched = $this->postFilter->apply(
            $query->select(['_id', 'JobCollection_Job', 'JobCollection_Estimate', 'JobCollection_Estimate_Price'])->get(),
            $this->postFilter->collectFormulaFilters($sub),
            $this->postFilter->collectVersionFilter($sub),
        );
        $matchedIds = $matched->pluck('_id')->map(fn ($id) => (string) $id)->all();

        return $this->repo->bulkUpdateByIds($matchedIds, $fields, $this->queryBuilder->roleScopeFilter());
    }
}
