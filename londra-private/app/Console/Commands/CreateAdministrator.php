<?php
declare(strict_types=1);
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
final class CreateAdministrator extends Command
{
    protected $signature='londra:create-administrator';
    protected $description='Create the single administrator with an interactive, hidden password prompt.';
    public function handle(): int
    {
        if(User::exists()){$this->error('An administrator already exists.');return self::FAILURE;}
        $data=['name'=>$this->ask('Administrator name'),'email'=>$this->ask('Administrator email'),'password'=>$this->secret('Password, at least 16 characters')];
        $validation=Validator::make($data,['name'=>['required','string','max:120'],'email'=>['required','email:rfc,filter','max:254'],'password'=>['required','string',Password::min(16)->mixedCase()->numbers()->symbols()]]);
        if($validation->fails()){foreach($validation->errors()->all() as $error){$this->error($error);}return self::FAILURE;}
        if($this->secret('Repeat password')!==$data['password']){$this->error('Passwords do not match.');return self::FAILURE;}
        User::create([...$data,'password'=>Hash::make($data['password'])]);
        $this->info('Administrator created. Optional authenticator setup is available in your profile.');return self::SUCCESS;
    }
}
