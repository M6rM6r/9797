import 'package:flutter/material.dart';

class BasicSearchBar extends StatelessWidget {
  final Function(String) onSearchChanged;
  final Function(String)? onSearchSubmitted;
  final String? hintText;
  final bool autofocus;

  const BasicSearchBar({
    super.key,
    required this.onSearchChanged,
    this.onSearchSubmitted,
    this.hintText,
    this.autofocus = false,
  });

  @override
  Widget build(BuildContext context) {
    return TextField(
      autofocus: autofocus,
      onChanged: onSearchChanged,
      onSubmitted: (value) {
        onSearchSubmitted?.call(value);
      },
      decoration: InputDecoration(
        hintText: hintText ?? 'ابحث عن كوبونات...',
        hintStyle: TextStyle(
          color: Colors.grey[600],
        ),
        prefixIcon: const Icon(Icons.search),
        suffixIcon: IconButton(
          icon: const Icon(Icons.clear),
          onPressed: () {
            onSearchChanged('');
          },
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
        ),
        filled: true,
        fillColor: Colors.grey[50],
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 12,
        ),
      ),
    );
  }
}
