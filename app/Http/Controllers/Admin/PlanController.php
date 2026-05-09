<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    
    public function plan()
    {
        $pageTitle = 'Plans';
        $plans = Plan::orderBy('price', 'asc')->paginate(getPaginate());
        return view('admin.plan.index', compact('pageTitle', 'plans'));
    }

    public function planSave(Request $request)
    {
        $this->validate($request, [
            'name'              => 'required',
            'price'             => 'required|numeric|min:0', 
            'bv'                => 'required|min:0|integer',
            'ref_com'           => 'required|numeric|min:0',
            'tree_com'          => 'required|numeric|min:0',
        ]);
        
        $plan = new Plan();
        if ($request->id) {
            $plan = Plan::findOrFail($request->id);
        }

        $plan->name             = $request->name;
        $plan->price            = $request->price;
        $plan->bv               = $request->bv;
        $plan->ref_com          = $request->ref_com;
        $plan->tree_com         = $request->tree_com;
        $plan->save();

        $notify[] = ['success', 'Plan saved successfully'];
        return back()->withNotify($notify);
    }

    public function status($id){
        return Plan::changeStatus( $id);
    }

}
