package com.smartdesk.backend.service;

import com.smartdesk.backend.exception.ConflictException;
import com.smartdesk.backend.model.User;
import com.smartdesk.backend.model.Worker;
import com.smartdesk.backend.repository.UserRepository;
import org.springframework.stereotype.Service;

@Service
public class UserService {

    private final UserRepository userRepo;

    public UserService(UserRepository userRepo) {
        this.userRepo = userRepo;
    }

    public User registerUser(String email, String password, User user) {
        if (userRepo.findByEmail(email).isPresent()) {
            throw new ConflictException("Email already registered");
        }
        user.setEmail(email);
        user.setPassword(password);
        return userRepo.save(user);
    }

    public User authenticate(String email, String password) {
        User user = userRepo.findByEmail(email)
                .orElseThrow(() -> new ConflictException("Invalid credentials"));
        if (!user.getPassword().equals(password)) {
            throw new ConflictException("Invalid credentials");
        }
        return user;
    }

    public void banWorker(Worker worker) {
        userRepo.deleteById(worker.getId());
    }
}
