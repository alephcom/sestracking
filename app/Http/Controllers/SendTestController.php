<?php

namespace App\Http\Controllers;

use App\Mail\SesMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SendTestController extends Controller
{
    public function index()
    {

        if(config('mail.default') != 'ses'){
            return response()->view('send_test.configure');
        }


        return response()->view('send_test.index');
    }

    public function send(Request $request)
    {
        // Validate the request data
       $validator = Validator::make($request->all(),[
            'sendFrom' => 'required|email',
            'sendTo' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
                return redirect()->back()
                    ->with('alert','Something went wrong, please check your input.')
                    ->withInput();  
        }

        // Create a new SesMail instance with the subject
        $mail = new SesMail(
                            subjectText: $request->subject,
                            fromEmail: $request->sendFrom,
                            fromName: 'Aleph',
                            data: ['message' => $request->message],
                            configurationSet: $request->configurationSet ?? ''
                        );

        // Send the email
        Mail::to($request->sendTo)->send($mail);

        return redirect()->back()->with('alert', 'Email sent successfully!');
    }       
}
