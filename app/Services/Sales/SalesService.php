<?php

namespace App\Services\Sales;

use App\Repositories\Sales\SalesRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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
    ) {
    }

    public function dashboard(Request $request): LengthAwarePaginator
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 50));
        $paginator = $this->queryBuilder->build($request)
            ->select(SalesRepository::DASHBOARD_LIST_FIELDS)
            ->orderBy('JobCollection_Reception_Date', 'desc')
            ->orderBy('JobCollection_Customer_Full_Name', 'desc')
            ->paginate($perPage);

        foreach ($paginator->items() as $item) {
            $item->Phone_Flag = $this->repo->countSamePhone($item->JobCollection_Customer_Phone) > 1;
        }
        return $paginator;
    }

    public function exportRows(Request $request): \Illuminate\Support\Collection
    {
        return $this->queryBuilder->build($request)
            ->select(SalesRepository::EXPORT_SELECT_FIELDS)
            ->orderBy('JobCollection_Reception_Date', 'desc')
            ->orderBy('JobCollection_Customer_Full_Name', 'desc')
            ->limit(5000)
            ->get();
    }

    public function assignSetter(array $ids, string $setterName): int
    {
        return $this->repo->bulkUpdateByIds($ids, [
            'JobCollection_Job_Setter_Full_Name' => $setterName,
        ]);
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
            return $this->repo->bulkUpdateByIds($ids, $fields);
        }
        if ($filterQuery === []) {
            return 0;
        }

        $sub = Request::create('', 'GET', $filterQuery);
        return (int) $this->queryBuilder->build($sub)->update($fields);
    }
}
