<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductCreateRequest;
use App\Http\Requests\ProductLinkRequest;
use App\Http\Requests\ProductUpdateRequest;
use App\Http\Utils\ErrorUtil;

use App\Http\Utils\UserActivityUtil;
use App\Models\Product;
use App\Models\ProductGallery;
use App\Models\ProductVariation;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    use ErrorUtil,UserActivityUtil;



 public function createProduct(ProductCreateRequest $request)
 {
     try{
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        return DB::transaction(function () use ($request) {
            if(!$request->user()->hasPermissionTo('product_create')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }
           $insertableData = $request->validated();
           $sku_prefix = "";
  // shop id is required if user is not super admin od data collector. and shop id means it is spacific to shop. so it is not default
           if(!empty($insertableData["shop_id"])) {
              $insertableData["is_default"] = false;
          $shop =   $this->shopOwnerCheck($insertableData["shop_id"]);
              if (!$shop) {
                  return response()->json([
                      "message" => "you are not the owner of the shop or the requested shop does not exist."
                  ], 401);
              }
              $sku_prefix = $shop->sku_prefix;
           } else {
              $insertableData["is_default"] = true;
           }


           $product =  Product::create($insertableData);

           if(empty($product->sku)){
              $product->sku = $sku_prefix .  str_pad($product->id, 4, '0', STR_PAD_LEFT);
          }
          $product->save();

           if($product->type == "single"){

              $product->product_variations()->create([

                      "sub_sku" => $product->sku,
                      "quantity" => $insertableData["quantity"],
                      "price" => $insertableData["price"],
                      "automobile_make_id" => NULL,


              ]);
           } else {
              foreach($insertableData["product_variations"] as $product_variation) {
                  $c = ProductVariation::
                  where('product_id', $product->id)
                  ->count() + 1;

                  $product->product_variations()->create([

                      "sub_sku" => $product->sku . "-" . $c,
                      "quantity" => $product_variation["quantity"],
                      "price" => $product_variation["price"],
                      "automobile_make_id" => $product_variation["automobile_make_id"],


              ]);

              }

           }

           if(!empty($insertableData["images"])) {
            foreach($insertableData["images"] as $product_image){
                ProductGallery::create([
                    "image" => $product_image,
                    "product_id" =>$product->id,
                ]);
            }
           }

           return response($product, 201);
        });


     } catch(Exception $e){
         error_log($e->getMessage());
     return $this->sendError($e,500,$request);
     }
 }

  public function updateProduct(ProductUpdateRequest $request)
  {

      try{
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        return DB::transaction(function () use ($request) {
            if(!$request->user()->hasPermissionTo('product_update')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }
            $updatableData = $request->validated();



        $product_prev = Product::where(
            [
"id" => $updatableData["id"],
            ]
        )
        ->first();
        if(!$product_prev) {

            return response()->json([
                "message" => "no product found"
            ],404);
        }

        if(!empty($product_prev->shop_id)) {
            $shop =   $this->shopOwnerCheck($product_prev->shop_id);
            if (!$shop) {
                return response()->json([
                    "message" => "you are not the owner of the shop or the requested shop does not exist."
                ], 401);
            }
        }

                $product  =  tap(Product::where(
                    [
                        "id" => $product_prev->id,
                        "shop_id" => $product_prev->shop_id
                                    ]
                ))->update(collect($updatableData)->only([
                  "name",
                  "sku",
                  "description",
                  "image",
                  // "is_active",
                  "is_default",
                  "product_category_id",
                //   "shop_id"

                ])->toArray()
                )
                    // ->with("somthing")

                    ->first();
                    if(!$product) {

                       return response()->json([
                           "message" => "no product found"
                       ],404);
                   }



           if($product->type == "single"){

              $product->product_variations()
              ->where([
                   "product_vairations.product_id" => $product->id
              ])
              ->update([

                      "sub_sku" => $product->sku,
                      "quantity" => $updatableData["quantity"],
                      "price" => $updatableData["price"],
                      "automobile_make_id" => NULL,
              ]);
           } else {
              foreach($updatableData["product_variations"] as $product_variation) {

                  if(empty($product_variation["id"])) {
                      $c = ProductVariation::
                      where('product_id', $product->id)
                      ->count() + 1;

                      $product->product_variations()->create([

                          "sub_sku" => $product->sku . "-" . $c,
                          "quantity" => $product_variation["quantity"],
                          "price" => $product_variation["price"],
                          "automobile_make_id" => $product_variation["automobile_make_id"],

                  ]);

                  } else {
                      $product->product_variations()
                      ->where([

                          "product_variations.id" => $product_variation["id"]
                      ])
                      ->update([

                          // "sub_sku" => $product->sku . "-" . $c,
                          "quantity" => $product_variation["quantity"],
                          "price" => $product_variation["price"],
                          "automobile_make_id" => $product_variation["automobile_make_id"],

                  ]);
                  }

              }
              }

              if(!empty($updatableData["images"])) {
                ProductGallery::where([
                    "product_id" =>$product->id,
                ])
                ->delete();
              }
              if(!empty($updatableData["images"])) {
                foreach($updatableData["images"] as $product_image){
                    ProductGallery::create([
                        "image" => $product_image,
                        "product_id" =>$product->id,
                    ]);
                }

              }


            return response($product, 201);
        });


      } catch(Exception $e){
          error_log($e->getMessage());
      return $this->sendError($e,500,$request);
      }
  }



  public function linkProductToShop(ProductLinkRequest $request)
  {

      try{
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        return DB::transaction(function () use ($request) {
            if(!$request->user()->hasPermissionTo('product_update')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }
            $updatableData = $request->validated();

            if (!$this->shopOwnerCheck($updatableData["shop_id"])) {
                return response()->json([
                    "message" => "you are not the owner of the shop or the requested shop does not exist."
                ], 401);
            }
            $shop =   $this->shopOwnerCheck($updatableData["shop_id"]);
            if (!$shop) {
                return response()->json([
                    "message" => "you are not the owner of the shop or the requested shop does not exist."
                ], 401);
            }

            $sku_prefix = $shop->sku_prefix;
            $updatableData["is_default"] = false;


                     $product =  Product::create($updatableData);

                     if(empty($product->sku)){
                        $product->sku = $sku_prefix .  str_pad($product->id, 4, '0', STR_PAD_LEFT);
                    }
                    $product->save();

                     if($product->type == "single"){

                        $product->product_variations()->create([

                                "sub_sku" => $product->sku,
                                "quantity" => $updatableData["quantity"],
                                "price" => $updatableData["price"],
                                "automobile_make_id" => NULL,


                        ]);
                     } else {
                        foreach($updatableData["product_variations"] as $product_variation) {
                            $c = ProductVariation::
                              where('product_id', $product->id)
                            ->count() + 1;

                            $product->product_variations()->create([

                                "sub_sku" => $product->sku . "-" . $c,
                                "quantity" => $product_variation["quantity"],
                                "price" => $product_variation["price"],
                                "automobile_make_id" => $product_variation["automobile_make_id"],


                        ]);

                        }




                     }



                     return response($product, 201);






        });


      } catch(Exception $e){
          error_log($e->getMessage());
      return $this->sendError($e,500,$request);
      }
  }



  public function getProducts($perPage,Request $request) {
    try{
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        if(!$request->user()->hasPermissionTo('product_view')){
            return response()->json([
               "message" => "You can not perform this action"
            ],401);
       }



        $productsQuery =  Product::with("product_variations");

        if(!empty($request->search_key)) {
            $productsQuery = $productsQuery->where(function($query) use ($request){
                $term = $request->search_key;
                $query->where("name", "like", "%" . $term . "%");
            });

        }

        if (!empty($request->product_category_id)) {
            $productsQuery = $productsQuery->where('product_category_id', $request->product_category_id);
        }

        if (!empty($request->start_date)) {
            $productsQuery = $productsQuery->where('created_at', ">=", $request->start_date);
        }

        if (!empty($request->end_date)) {
            $productsQuery = $productsQuery->where('created_at', "<=", ($request->end_date . ' 23:59:59'));
        }


        $products = $productsQuery->orderByDesc("id")->paginate($perPage);

        return response()->json($products, 200);
    } catch(Exception $e){

    return $this->sendError($e,500,$request);
    }
}



  public function getProductById($id,Request $request) {
    try{
        $this->storeActivity($request, "DUMMY activity","DUMMY description");
        if(!$request->user()->hasPermissionTo('product_view')){
            return response()->json([
               "message" => "You can not perform this action"
            ],401);
       }

        $product =  Product::where([
            "id" => $id
        ])
        ->first()
        ;
        if(!$product) {

return response()->json([
   "message" => "no product found"
],404);
        }

        return response()->json($product, 200);
    } catch(Exception $e){

    return $this->sendError($e,500,$request);
    }
}



    public function deleteProductById($id,Request $request) {

        try{
            $this->storeActivity($request, "DUMMY activity","DUMMY description");
            if(!$request->user()->hasPermissionTo('product_delete')){
                return response()->json([
                   "message" => "You can not perform this action"
                ],401);
           }
           Product::where([
            "id" => $id
           ])
           ->delete();

            return response()->json(["ok" => true], 200);
        } catch(Exception $e){

        return $this->sendError($e,500,$request);
        }

    }




}
