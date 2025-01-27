<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductCategoryCreateRequest;
use App\Http\Requests\ProductCategoryUpdateRequest;
use App\Http\Utils\ErrorUtil;
use App\Http\Utils\UserActivityUtil;
use App\Models\ProductCategory;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    use ErrorUtil,UserActivityUtil;




 public function createProductCategory(ProductCategoryCreateRequest $request)
 {
     try{

        $this->storeActivity($request, "DUMMY activity","DUMMY description");

         if(!$request->user()->hasPermissionTo('product_category_create')){
              return response()->json([
                 "message" => "You can not perform this action"
              ],401);
         }

         $insertableData = $request->validated();

         $product_category =  ProductCategory::create($insertableData);



         return response($product_category, 201);
     } catch(Exception $e){
         error_log($e->getMessage());
     return $this->sendError($e,500,$request);
     }
 }

 public function updateProductCategory(ProductCategoryUpdateRequest $request)
 {

     try{
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
         if(!$request->user()->hasPermissionTo('product_category_update')){
             return response()->json([
                "message" => "You can not perform this action"
             ],401);
        }
         $updatableData = $request->validated();



             $product_category  =  tap(ProductCategory::where(["id" => $updatableData["id"]]))->update(collect($updatableData)->only([
                 'name',
                 'image',
                 'icon',
                 "description",

             ])->toArray()
             )
                 // ->with("somthing")

                 ->first();
                 if(!$product_category) {

                    return response()->json([
                        "message" => "no product category found"
                    ],404);
                }

         return response($product_category, 201);
     } catch(Exception $e){
         error_log($e->getMessage());
     return $this->sendError($e,500,$request);
     }
 }



 public function getProductCategories($perPage,Request $request) {
     try{
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
         if(!$request->user()->hasPermissionTo('product_category_view')){
             return response()->json([
                "message" => "You can not perform this action"
             ],401);
        }



         $productCategoriesQuery = new ProductCategory();

         if(!empty($request->search_key)) {
             $productCategoriesQuery = $productCategoriesQuery->where(function($query) use ($request){
                 $term = $request->search_key;
                 $query->where("name", "like", "%" . $term . "%");
             });

         }

         if (!empty($request->start_date)) {
             $productCategoriesQuery = $productCategoriesQuery->where('created_at', ">=", $request->start_date);
         }

         if (!empty($request->end_date)) {
             $productCategoriesQuery = $productCategoriesQuery->where('created_at', "<=", ($request->end_date . ' 23:59:59'));
         }


         $product_categories = $productCategoriesQuery->orderByDesc("id")->paginate($perPage);

         return response()->json($product_categories, 200);
     } catch(Exception $e){

     return $this->sendError($e,500,$request);
     }
 }


 public function getProductCategoryById($id,Request $request) {
     try{
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
         if(!$request->user()->hasPermissionTo('product_category_view')){
             return response()->json([
                "message" => "You can not perform this action"
             ],401);
        }

         $product_category =  ProductCategory::where([
             "id" => $id
         ])
         ->first()
         ;
         if(!$product_category) {

return response()->json([
    "message" => "no product category found"
],404);
         }

         return response()->json($product_category, 200);
     } catch(Exception $e){

     return $this->sendError($e,500,$request);
     }
 }



 

 public function getAllProductCategory(Request $request) {
     try{

        $this->storeActivity($request, "DUMMY activity","DUMMY description");
         $productCategoriesQuery = new ProductCategory();

         if(!empty($request->search_key)) {
             $productCategoriesQuery = $productCategoriesQuery->where(function($query) use ($request){
                 $term = $request->search_key;
                 $query->where("name", "like", "%" . $term . "%");
             });

         }

         if (!empty($request->start_date)) {
             $productCategoriesQuery = $productCategoriesQuery->where('created_at', ">=", $request->start_date);
         }
         if (!empty($request->end_date)) {
             $productCategoriesQuery = $productCategoriesQuery->where('created_at', "<=", ($request->end_date . ' 23:59:59'));
         }

         $product_categories = $productCategoriesQuery->orderByDesc("name")->get();
         return response()->json($product_categories, 200);
     } catch(Exception $e){

     return $this->sendError($e,500,$request);
     }

 }


    public function deleteProductCategoryById($id,Request $request) {

        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if(!$request->user()->hasPermissionTo('product_category_delete')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }
           ProductCategory::where([
            "id" => $id
           ])
           ->delete();

            return response()->json(["ok" => true], 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }

    }

}
