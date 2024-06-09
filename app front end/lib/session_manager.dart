import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

class SessionManager {
  static const String _accessTokenKey = 'accessToken';
  static const String _refreshTokenKey = 'refreshToken';

  static Future<void> saveSession(Map<String, dynamic> sessionData) async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.setString(_accessTokenKey, sessionData['access_token']);
    await prefs.setString(_refreshTokenKey, sessionData['refresh_token']);
  }

  static Future<Map<String, String?>> getSession() async {
    SharedPreferences prefs = await SharedPreferences.getInstance();
    String? accessToken = prefs.getString(_accessTokenKey);
    String? refreshToken = prefs.getString(_refreshTokenKey);
    return {'accessToken': accessToken, 'refreshToken': refreshToken};
  }
}
