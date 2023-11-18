<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\transaction;
use App\Models\payment;
use Validator;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\PaymentResource;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Auth;
use DB;

class ApiController extends BaseController
{
    //
    /**
     * add Transaction to the company as admin
     */
    public function add_transaction(Request $request): JsonResponse
    {
        $user=auth()->id();
        $is_admin= json_decode(Auth::user());
        $input = $request->all();
       // dd($is_admin);
       
        if($is_admin->is_admin==1)  
        {
            $validator = Validator::make($input, [
                'payer'            => 'required',
                'amount'           => 'required',
                'due_on'           => 'required',
                'vat'              => 'required', 
                'is_vat_inclusive' => 'required'
            ]);
    
            if($validator->fails()){
                return $this->sendError('Validation Error.', $validator->errors());       
            }
            $status=$this->mangeStatus($request->due_on);
            $transaction = transaction::create([
                'user_id'=>$request->payer,
                'amount' =>$request->amount,
                'due_date' =>$request->due_on,
                'vat' => $request->vat,
                'is_vat_include'=>$request->is_vat_inclusive,
                'status' => $status

            ]);
    
            return $this->sendResponse(new TransactionResource($transaction), 'Transaction created successfully.');
        } else{
            return $this->sendError('Authorization  Error.', "You're not allowed to do this action");       

        }
} 
    /**
     * compare date of transaction with current date to set the status
     */
    public function mangeStatus($due_date)
    {
        $date = Carbon::parse($due_date)->format('m/d/Y H:i:s');
         $compare_date = Carbon::createFromFormat('m/d/Y H:i:s',$date);

       // dd($compare_date);
         $today = Carbon::now();

  
        $result = $compare_date->lessThan($today);
        if($result)
        {
            return 'Overdue';
        }else{
            return 'outstanding';
        }
    }
    /**
     * add Payment to specific transaction as admin
     */
    public function add_payment(Request $request)
    {
        $user=auth()->id();
        $is_admin= json_decode(Auth::user());
        
       // dd($is_admin);
        
        $input = $request->all();
        if($is_admin->is_admin==1)  
        {
            $validator = Validator::make($input, [
                'transaction_id'   => 'required',
                'amount'           => 'required',
                'paid_on'           => 'required'
            
            ]);
    
            if($validator->fails()){
                return $this->sendError('Validation Error.', $validator->errors());       
            }
            $payment = payment::create([
                'transaction_id'=>$request->transaction_id,
                'amount' =>$request->amount,
                'pay_date' =>$request->paid_on,
                'details' => $request->details
                

            ]);
            $status=$this->paymentStatus($request->transaction_id);

            $changeStatus=transaction::find($request->transaction_id);
            $changeStatus->status=$status;
            $changeStatus->save();
            return $this->sendResponse(new PaymentResource($payment), 'Payment created successfully.');
        }else{
            return $this->sendError('Authorization  Error.', "You're not allowed to do this action");       
        }

    }
    /**
     * Compare payment with transaction to mange status
     */
    public function paymentStatus($transaction_id)
    {
        $totalPayment=payment::where('transaction_id',$transaction_id)->sum('amount');
        //dd($totalPayment);
        $transaction=transaction::find($transaction_id);
       // dd($transaction);
       echo 'TotalPayment ='.floatval($totalPayment)." -----  Transaction amount =".floatval($transaction->amount);
     
        if(floatval($transaction->amount)==floatval($totalPayment))
        {
            return 'Paid';
        }else{
            return $this->mangeStatus($transaction->due_date);

        }
    }
    /**
     * View Transaction upon rule as system user
     */
    public function view_transaction(Request $request,$due_date="")
    {
        $user=auth()->id();
        $is_admin= json_decode(Auth::user());
        
        
        $input = $request->all();
        if($is_admin->is_admin<>1)
        $query=transaction::where('user_id',$user)->get();
        else         $query=transaction::all();
       
       $transactions=[];
       foreach($query as $q)
       {
      
        $pay=payment::where('transaction_id',$q->id)->get();
        $payments=[];
        foreach($pay as $pay)
        {
            $payments[]= [
                'id' => $pay->id,
                'transaction_id' => $pay->transaction_id,
                'pay_date' => $pay->pay_date,
                'amount' => $pay->amount,
                'details'=> $pay->details,
                'created_at' => $pay->created_at->format('d/m/Y'),
                'updated_at' => $pay->updated_at->format('d/m/Y'),
            ];
        }
        $transactions[]=[ 'id' => $q->id,
        'user_id' => $q->user_id,
        'due_date' => $q->due_date,
        'amount' => $q->amount,
        'vat' => $q->vat,
        'is_vat_include' => $q->is_vat_include,
        'status'=>$q->status,
        'created_at' => $q->created_at->format('d/m/Y'),
        'updated_at' => $q->updated_at->format('d/m/Y'),
        'payments'=>$payments];

       }
        return $this->sendResponse($transactions, 'Transaction listed successfully.');


    }
    /**
     * generate monthly reports as admin
     */
    public function monthly_report(Request $request,$from="",$to="")
    {
        $user=auth()->id();
        $is_admin= json_decode(Auth::user());
        if($is_admin->is_admin==1)  
        { 
            $transacation_report=transaction::select(DB::raw(" Month(due_date) as monthNum ,Year(due_date) as yearNum,IF(`status`='paid',sum(amount),0) as `paid`,IF(`status`='outStanding',sum(amount),0) as `outStanding`,IF(`status`='overdue',sum(amount),0) as `overdue`")) ->groupByRaw('Month(due_date)');
            if($from!='' && $to!=''){
                $transacation_report-> whereDate('due_date', '>=', $from)
                ->whereDate('due_date', '<=', $to);
            }
        
            $report= $transacation_report->get();
            // dd($report);
            $reports=[];
            foreach($report as $repo)
            {
            //  dd($repo);
                    $reports[]=[
                        'month'=>$repo->monthNum,
                        'year'=>$repo->yearNum,
                        'paid'=>$repo->paid,
                        'outstanding'=>$repo->outStanding,
                        'overdue'=>$repo->overdue
                    ];
            }
            if(count($reports)>0)
            $msg='Report records is listed successfully.';
            else $msg="There's no record to your report for this period .";
        
            return $this->sendResponse($reports, $msg);
        }else{
            return $this->sendError('Authorization  Error.', "You're not allowed to do this action");       
 
        }

    }
    
}
