// verification_screen.dart
// ignore_for_file: use_build_context_synchronously

import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/material.dart';
import 'constants.dart';

class VerificationScreen extends StatefulWidget {
  const VerificationScreen({Key? key}) : super(key: key);

  @override
  _VerificationScreenState createState() => _VerificationScreenState();
}

class _VerificationScreenState extends State<VerificationScreen> {
  final _formKey = GlobalKey<FormState>();
  String _code = '';

  void _submit() async {
    if (_formKey.currentState?.validate() == true) {
      _formKey.currentState?.save();
      var headers = {'Content-Type': 'application/json'};
      var request =
          http.Request('POST', Uri.parse('${Constants.baseUrl}/client/verify'));
      request.body = json.encode({"code": _code.trim()});
      request.headers.addAll(headers);

      http.StreamedResponse response = await request.send();

      if (response.statusCode == 201) {
        print(await response.stream.bytesToString());
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Verifying Code...')),
        );
        Navigator.pushNamed(context, '/login');
      } else {
        print(response.reasonPhrase);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Error Verifying Code...')),
        );
      }

      // Here you might navigate to another screen if verification is successful
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                SizedBox(
                    height: MediaQuery.of(context).size.height *
                        0.2), // Space from top
                const Text(
                  'Verify Your Account',
                  style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 50),
                TextFormField(
                  decoration: InputDecoration(
                    labelText: 'Verification Code',
                    hintText: 'Enter your code',
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Please enter your verification code';
                    }
                    // You can add more specific validation for the verification code format if needed
                    return null;
                  },
                  onSaved: (value) => _code = value ?? '',
                ),
                const SizedBox(height: 40),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.black, // Background color
                    foregroundColor: Colors.white, // Text Color
                    minimumSize: const Size(double.infinity, 60),
                    padding: const EdgeInsets.symmetric(
                        horizontal: 50, vertical: 20),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  onPressed: _submit,
                  child: const Text(
                    'Verify',
                    style: TextStyle(fontSize: 18),
                  ),
                ),
                const SizedBox(height: 20),
                // TextButton(
                //   onPressed: () {
                //     Navigator.pushNamed(context, '/login');
                //     // Optionally add functionality to resend verification code
                //   },
                //   child: const Text('Resend Code'),
                // ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
