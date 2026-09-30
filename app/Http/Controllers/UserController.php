<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()
                ->withCount(['assignedTickets', 'assignedTickets as open_tickets_count' => fn ($query) => $query->open()])
                ->orderBy('name')
                ->get(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('user', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'string', Password::min(8)],
        ], attributes: ['name' => 'họ tên', 'email' => 'email', 'role' => 'quyền', 'password' => 'mật khẩu']);

        User::create([...$data, 'is_active' => true]);

        return back()->with('status', "Đã thêm tài khoản {$data['name']}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $bag = 'user'.$user->id;
        $data = $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'string', Password::min(8)],
        ], attributes: ['name' => 'họ tên', 'email' => 'email', 'role' => 'quyền', 'password' => 'mật khẩu mới']);

        if ($user->is($request->user()) && $data['role'] !== UserRole::Admin->value) {
            return back()->withInput()->withErrors(['role' => 'Không tự bỏ quyền Admin của chính mình.'], $bag);
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = UserRole::from($data['role']);

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();

        return back()->with('status', "Đã lưu tài khoản {$user->name}.");
    }

    /**
     * Hand every open ticket someone is in charge of to a colleague, e.g. while they are on leave.
     */
    public function handover(Request $request, User $user): RedirectResponse
    {
        $bag = 'handover'.$user->id;
        $data = $request->validateWithBag($bag, [
            'to_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true), Rule::notIn([$user->id])],
        ], attributes: ['to_user_id' => 'người nhận']);

        $to = User::findOrFail($data['to_user_id']);
        $moved = $user->assignedTickets()->open()->update(['technician_id' => $to->id]);

        return back()->with('status', "Đã chuyển {$moved} phiếu đang mở của {$user->name} sang {$to->name}.");
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'Không tự khoá tài khoản đang đăng nhập.');

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status', ($user->is_active ? 'Đã mở khoá ' : 'Đã khoá ').$user->name.'.');
    }
}
