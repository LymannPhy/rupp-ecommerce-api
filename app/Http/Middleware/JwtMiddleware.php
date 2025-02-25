<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            return response()->json([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Token Expired ⏳',
                'errors' => [
                    'details' => "Oops! Your session has expired. 🕰️ Time flies when you're having fun, right? Try logging in again to refresh your token. 🚀"
                ],
                'date' => now()->toDateTimeString(),
            ], Response::HTTP_UNAUTHORIZED);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Invalid Token ❌',
                'errors' => [
                    'details' => "Uh-oh! 🤯 That token doesn’t look right. Maybe it got scrambled in transmission? Try logging in again and getting a fresh one. 🔐"
                ],
                'date' => now()->toDateTimeString(),
            ], Response::HTTP_UNAUTHORIZED);
        } catch (TokenBlacklistedException $e) {
            return response()->json([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Blacklisted Token 🚫',
                'errors' => [
                    'details' => "This token has been blacklisted. 🔥 Looks like it's been used before! Try logging in again to get a fresh token. 🎟️"
                ],
                'date' => now()->toDateTimeString(),
            ], Response::HTTP_UNAUTHORIZED);
        } catch (JWTException $e) {
            return response()->json([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Token Not Found 🧐',
                'errors' => [
                    'details' => "Hmmm... 🤔 We couldn’t find a token in your request. Did you forget to include it in the header? Please add a valid token and try again! 🔑"
                ],
                'date' => now()->toDateTimeString(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
