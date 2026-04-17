package com.smartdesk.backend.service;

import com.smartdesk.backend.exception.NotFoundException;
import com.smartdesk.backend.repository.UserRepository;
import org.springframework.stereotype.Service;

import java.util.List;

@Service
public class SysAdminService {

    private final UserRepository userRepo;

    public SysAdminService(UserRepository userRepo) {
        this.userRepo = userRepo;
    }

    public void banUser(Long userId) {
        if (!userRepo.existsById(userId)) {
            throw new NotFoundException("User not found");
        }
        userRepo.deleteById(userId);
    }

    public List<String> getSystemLogs() {
        return List.of("System healthy", "No critical alarms");
    }
}
